<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve explicitly selected profile, CV and badge data for an authorised item.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_linked_content {
    /** @var string[] Linked block types. */
    public const TYPES = ['personal', 'cv', 'badge'];

    /** @var array CV record types, labels and display fields. Table names never come from requests. */
    private const RECORDS = [
        'edu' => ['education_history', ['qualname', 'qualtype', 'institution'], 'qualdescription'],
        'employ' => ['employment_history', ['jobtitle', 'employer'], 'positiondescription'],
        'certif' => ['certif', ['title'], 'description'],
        'public' => ['public', ['title', 'contribution'], 'contributiondetails'],
        'mbrship' => ['mbrship', ['title'], 'description'],
    ];

    /** @var string[] Rich-text fields in the central CV. */
    private const FIELDS = [
        'cover', 'interests', 'goalspersonal', 'goalsacademic', 'goalscareers',
        'skillspersonal', 'skillsacademic', 'skillscareers',
    ];

    /**
     * Return source records belonging to the item owner, without creating a CV on read.
     *
     * @param string $type Block type.
     * @param int $ownerid Trusted item owner.
     * @return array Source records keyed by stable selection identifiers.
     */
    private static function sources(string $type, int $ownerid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $ownerid, 'deleted' => 0]);
        if (!$user) {
            return [];
        }
        if ($type === 'badge') {
            $sources = [];
            foreach (\block_exaport_get_all_user_badges($ownerid) as $badge) {
                $sources[(string)$badge->id] = ['label' => $badge->name, 'badge' => $badge];
            }
            return $sources;
        }
        $resume = $DB->get_record('block_exaportresume', ['user_id' => $ownerid]);
        if ($type === 'personal') {
            $sources = [];
            foreach (['firstname', 'lastname', 'email'] as $field) {
                $sources[$field] = ['label' => get_string($field), 'value' => $user->{$field}];
            }
            $sources['picture'] = ['label' => get_string('profilepicture', 'block_exaport'), 'user' => $user];
            if ($resume) {
                $sources['cover'] = self::cv_field($resume, 'cover');
            }
            return $sources;
        }
        if ($type !== 'cv' || !$resume) {
            return [];
        }
        $sources = [];
        $context = \context_user::instance($ownerid);
        $fs = get_file_storage();
        foreach (self::FIELDS as $field) {
            $source = self::cv_field($resume, $field);
            // A section can contain attachments even when its text is empty.
            if (trim($source['html']) !== '' || !$fs->is_area_empty($context->id, 'block_exaport',
                    $source['attachmentarea'], $source['attachmentid'])) {
                $sources[$field] = $source;
            }
        }
        foreach (self::RECORDS as $recordtype => [$section, $fields, $description]) {
            $label = get_string('cofigureblock_cvinfo_' . $section, 'block_exaport');
            $records = $DB->get_records('block_exaportresume_' . $recordtype,
                ['resume_id' => $resume->id], 'sorting ASC, id ASC');
            foreach ($records as $record) {
                $title = implode(' — ', array_filter(array_map(static function($field) use ($record) {
                    return trim((string)($record->{$field} ?? ''));
                }, $fields)));
                $sources[$recordtype . ':' . $record->id] = [
                    'label' => $label . ': ' . $title,
                    'record' => $record,
                    'description' => $description,
                    'attachmentarea' => 'resume_' . $recordtype,
                    'attachmentid' => (int)$record->id,
                ];
            }
        }
        return $sources;
    }

    /**
     * Describe one central CV field and its existing file areas.
     *
     * @param \stdClass $resume Owner's CV.
     * @param string $field Allowlisted CV field.
     * @return array
     */
    private static function cv_field(\stdClass $resume, string $field): array {
        return [
            'label' => get_string('resume_' . $field, 'block_exaport'),
            'html' => (string)($resume->{$field} ?? ''),
            'editorarea' => 'resume_editor_' . $field,
            'editorid' => (int)$resume->id,
            'attachmentarea' => 'resume_' . $field,
            'attachmentid' => (int)$resume->id,
        ];
    }

    /**
     * Choices for the owner-only picker. Values are escaped by Moodle's form elements.
     *
     * @param string $type Block type.
     * @param int $ownerid Item owner.
     * @return array
     */
    public static function options(string $type, int $ownerid): array {
        return array_map(static function(array $source): string {
            return trim(strip_tags($source['label']));
        }, self::sources($type, $ownerid));
    }

    /**
     * Validate selections against the trusted owner, including forged form submissions.
     *
     * Only identifiers are stored. The owner guard prevents copied/imported identifiers
     * from accidentally publishing the destination user's profile or unrelated CV entries.
     *
     * @param string $type Block type.
     * @param int $ownerid Item owner.
     * @param array $selection Explicitly selected keys.
     * @param bool $withfiles Whether selected CV attachments are included.
     * @return string JSON configuration stored in the block's content column.
     */
    public static function configuration(string $type, int $ownerid, array $selection, bool $withfiles = false): string {
        $options = self::options($type, $ownerid);
        foreach ($selection as $key) {
            if (!is_string($key) && !is_int($key)) {
                throw new \invalid_parameter_exception('Selection identifiers must be strings or integers');
            }
        }
        $selection = array_values(array_unique(array_map('strval', $selection)));
        if (!$selection || array_diff($selection, array_keys($options))) {
            throw new \invalid_parameter_exception('Select available information belonging to the item owner');
        }
        return json_encode(['ownerid' => $ownerid, 'selection' => $selection,
            'withfiles' => $type === 'cv' && $withfiles]);
    }

    /**
     * Read a stored selection. Malformed or foreign configurations resolve to nothing.
     *
     * @param \stdClass $block Content block.
     * @param int $ownerid Trusted item owner.
     * @return array
     */
    public static function selection(\stdClass $block, int $ownerid): array {
        $data = json_decode((string)($block->content ?? ''), true);
        if (!is_array($data) || ($data['ownerid'] ?? null) !== $ownerid ||
                !is_array($data['selection'] ?? null) ||
                count(array_filter($data['selection'], 'is_string')) !== count($data['selection'])) {
            return ['selection' => [], 'withfiles' => false];
        }
        return $data;
    }

    /**
     * Resolve selected data to safe HTML and the exact files that this block may expose.
     *
     * The caller must authorise the parent item first. File placeholders use unique file
     * IDs so multiple selected entries can contain attachments with the same filename.
     * Exporters can materialise this result as a portable text snapshot.
     *
     * @param \stdClass $block Content block.
     * @param int $ownerid Trusted item owner.
     * @return array{content: string, editorfiles: array, editorfilereferences: array}
     */
    public static function resolve(\stdClass $block, int $ownerid): array {
        $data = self::selection($block, $ownerid);
        $sources = self::sources($block->type, $ownerid);
        $result = ['content' => '', 'editorfiles' => [], 'editorfilereferences' => []];
        $context = \context_user::instance($ownerid);
        $fs = get_file_storage();
        if ($block->type === 'personal') {
            $name = [];
            foreach (['firstname', 'lastname'] as $field) {
                if (in_array($field, $data['selection'], true) && isset($sources[$field])) {
                    $name[] = s($sources[$field]['value']);
                }
            }
            if ($name) {
                $result['content'] = \html_writer::div(implode(' ', $name), 'exaport-linked-content-entry mb-3');
            }
        }
        foreach ($data['selection'] as $key) {
            if (!is_string($key) || !isset($sources[$key])) {
                continue;
            }
            if ($block->type === 'personal' && in_array($key, ['firstname', 'lastname'], true)) {
                continue;
            }
            $source = $sources[$key];
            $html = '';
            if (isset($source['value'])) {
                $html = \html_writer::div(s($source['value']));
            } else if (isset($source['user'])) {
                if ($source['user']->picture) {
                    $icons = $fs->get_area_files($context->id, 'user', 'icon', 0, 'filename ASC', false);
                    foreach ($icons as $icon) {
                        if (in_array($icon->get_filename(), ['f1.png', 'f1.jpg'], true)) {
                            $html = \html_writer::empty_tag('img', ['src' => self::add_file($icon, $result),
                                'alt' => get_string('profilepicture', 'block_exaport'), 'width' => 100, 'height' => 100]);
                            break;
                        }
                    }
                }
            } else if (isset($source['badge'])) {
                $badge = $source['badge'];
                $badgecontext = empty($badge->courseid) ? \context_system::instance() :
                    \context_course::instance($badge->courseid);
                foreach ($fs->get_area_files($badgecontext->id, 'badges', 'badgeimage', $badge->id,
                        'filename ASC', false) as $image) {
                    if (in_array($image->get_filename(), ['f1.png', 'f1.jpg'], true)) {
                        $html .= \html_writer::empty_tag('img', ['src' => self::add_file($image, $result),
                            'alt' => strip_tags($badge->name), 'width' => 100, 'height' => 100]);
                        break;
                    }
                }
                $html .= self::clean_html($badge->description, $badgecontext);
                $html .= \html_writer::div(s(get_string('linkedbadgeissued', 'block_exaport', userdate($badge->dateissued))));
                if (!empty($badge->dateexpire)) {
                    $html .= \html_writer::div(s(get_string('linkedbadgeexpires', 'block_exaport', userdate($badge->dateexpire))));
                }
            } else if (isset($source['record'])) {
                $record = $source['record'];
                $dates = array_filter([$record->startdate ?? $record->date ?? '', $record->enddate ?? '']);
                $html .= \html_writer::div(s(implode(' — ', $dates)));
                $html .= self::clean_html((string)($record->{$source['description']} ?? ''), $context);
                $url = clean_param((string)($record->url ?? ''), PARAM_URL);
                if ($url !== '') {
                    $html .= \html_writer::div(\html_writer::link($url, s($url), ['rel' => 'noopener noreferrer']));
                }
            } else if (isset($source['html'])) {
                $html = $source['html'];
                foreach ($fs->get_area_files($context->id, 'block_exaport', $source['editorarea'],
                        $source['editorid'], 'filepath ASC, filename ASC', false) as $file) {
                    $path = ltrim($file->get_filepath(), '/') . $file->get_filename();
                    $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
                    $references = array_map(static function(string $path): string {
                        return preg_quote('@@PLUGINFILE@@/' . $path, '~');
                    }, [$path, $encoded]);
                    // Match a complete URL, so referencing photo.png.backup cannot expose photo.png.
                    $pattern = '~(?:' . implode('|', $references) . ')(?=["\'\s<>?#]|$)~u';
                    if (preg_match($pattern, $html)) {
                        $replacement = self::add_file($file, $result);
                        $html = preg_replace_callback($pattern, static function() use ($replacement): string {
                            return $replacement;
                        }, $html);
                    }
                }
                $html = self::clean_html($html, $context);
            }
            if (!empty($data['withfiles']) && isset($source['attachmentarea'])) {
                foreach ($fs->get_area_files($context->id, 'block_exaport', $source['attachmentarea'],
                        $source['attachmentid'], 'filepath ASC, filename ASC', false) as $file) {
                    $html .= \html_writer::div(\html_writer::link(self::add_file($file, $result), s($file->get_filename())));
                }
            }
            if ($html !== '') {
                $heading = $block->type === 'personal' ? '' : \html_writer::tag('h4', s($source['label']));
                $result['content'] .= \html_writer::div($heading . $html, 'exaport-linked-content-entry mb-3');
            }
        }
        return $result;
    }

    /**
     * Register a file only after its owning source has been selected.
     *
     * @param \stored_file $file Selected source file.
     * @param array $result Current projection.
     * @return string Portable file placeholder.
     */
    private static function add_file(\stored_file $file, array &$result): string {
        $reference = $file->get_id() . '/' . $file->get_filename();
        $result['editorfiles'][$file->get_id()] = $file;
        $result['editorfilereferences'][$file->get_id()] = $reference;
        // Storage/export metadata stays raw; only the filename in the HTML URL is encoded.
        return '@@PLUGINFILE@@/' . $file->get_id() . '/' . rawurlencode($file->get_filename());
    }

    /**
     * Clean source HTML even when the viewer has Moodle's trust-content capability.
     *
     * @param string $html Source content.
     * @param \context $context Source context.
     * @return string
     */
    private static function clean_html(string $html, \context $context): string {
        return format_text($html, FORMAT_HTML, ['context' => $context, 'filter' => false, 'noclean' => false]);
    }

    /**
     * Render using the parent item's authorization route, never a general CV access link.
     *
     * @param \stdClass $block Content block.
     * @param int $ownerid Item owner.
     * @param string $access Parent item access route.
     * @return string
     */
    public static function render(\stdClass $block, int $ownerid, string $access = ''): string {
        $data = self::resolve($block, $ownerid);
        $route = (trim($access, '/') !== '' ? trim($access, '/') . '/' : '') .
            'itemid/' . $block->itemid . '/blockid/' . $block->id;
        return file_rewrite_pluginfile_urls($data['content'], 'pluginfile.php',
            \context_user::instance($ownerid)->id, 'block_exaport', 'item_content_linked', $route);
    }
}
