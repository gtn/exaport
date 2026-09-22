<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Presentation model for the sharing settings used by views, categories and items.
 *
 * Entity-specific code remains responsible for permissions and persistence. This class deliberately
 * normalises only the presentation state, preventing three copies of the same nested form markup.
 */
final class sharing_form implements \renderable, \templatable {
    /** @var array Template context. */
    private $context;

    /**
     * @param array $context Normalised sharing form context.
     */
    public function __construct(array $context) {
        $defaults = [
            'componentid' => 'exaport-sharing',
            'enabled' => false,
            'configured' => false,
            'showmaster' => true,
            'showexternal' => false,
            'externalchecked' => false,
            'externalinput' => '',
            'externalurl' => '',
            'showexternalcomments' => false,
            'externalcommentschecked' => false,
            'showinternal' => true,
            'internalchecked' => true,
            'internalinput' => '',
            'showeveryone' => false,
            'mode' => 0,
            'showsearch' => false,
            'searchvalue' => '',
            'showemail' => false,
            'emailchecked' => false,
            'emailinput' => '',
            'emailaddresses' => '',
            'alwaysnotify' => false,
        ];
        $this->context = array_merge($defaults, $context);
        $this->context['modeall'] = (int)$this->context['mode'] === 1;
        $this->context['modeusers'] = (int)$this->context['mode'] === 0;
        $this->context['modegroups'] = (int)$this->context['mode'] === 2;
    }

    /**
     * Export the template context.
     *
     * @param \renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        return $this->context;
    }
}
