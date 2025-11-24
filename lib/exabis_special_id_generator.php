<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_exaport {

    defined('MOODLE_INTERNAL') || die();

    class exabis_special_id_generator {
        /*
        generates a 25 digit id
        21 digits = unique id (base 64 = A-Za-z0-9_-)
        4 digits = checksum (crc32 of id in base 64)
        */

        const ID_LENGTH = 21;
        const CHECK_LENGTH = 4;
        const BASE = 64;
        private static $BASE64 = array(
            "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z",
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z",
            "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "_", "-");

        static private function str_baseconvert($str, $frombase = 10, $tobase = 36) {
            // convert to binary
            if ($frombase == 16) {
                // base 16, use own logic.
                // because numbers are large and can't find in an integer!
                $binary = '';
                for ($i = 0; $i < strlen($str); $i++) {
                    $binary .= sprintf("%0" . ($frombase / 2) . "d", base_convert($str[$i], $frombase, 2));
                }
            } else if ($frombase == 10) {
                // our base 10 numbers are small, they fit in an integer.
                $binary = base_convert($str, $frombase, 2);
            } else {
                die("wrong base $frombase");
            }

            if ($tobase != 64) {
                die("only base64 supported for now");
            }

            // delete leading zeros
            $binary = ltrim($binary, '0');

            // make length
            $part_length = log($tobase, 2);
            $length = ceil(strlen($binary) / $part_length) * $part_length;
            $binary = str_pad($binary, $length, '0', STR_PAD_LEFT);

            $ret = '';
            $part_i = 0;
            $val = 0;

            for ($i = 0; $i < strlen($binary); $i++) {
                $val = $val * 2 + $binary[$i];

                $part_i++;
                if ($part_i < $part_length) {
                    continue;
                }

                if ($tobase == 64) {
                    $val = self::$BASE64[$val];
                }
                $ret .= $val;
                $val = 0;
                $part_i = 0;
            }

            return $ret;
        }

        /* from http://php.net/manual/de/function.base-convert.php */
        /*
        static private function str_baseconvert_bcmath($str, $frombase=10, $tobase=36) {
            $str = trim($str);
            if (intval($frombase) != 10) {
                $len = strlen($str);
                $q = 0;
                for ($i=0; $i<$len; $i++) {
                    $r = base_convert($str[$i], $frombase, 10);
                    $q = bcadd(bcmul($q, $frombase), $r);
                }
            }
            else $q = $str;

            if (intval($tobase) != 10) {
                $s = '';
                while (bccomp($q, '0', 0) > 0) {
                    $r = intval(bcmod($q, $tobase));
                    if ($tobase == 64) {
                        $s = self::$BASE64[$r].$s;
                    } else {
                        $s = base_convert($r, 10, $tobase) . $s;
                    }
                    $q = bcdiv($q, $tobase, 0);
                }
            }
            else $s = $q;

            return $s;
        }
        */

        // make a string longer/shorter but cutting, or adding zeros to the left
        static private function make_length($str, $len) {
            return str_pad(substr($str, -$len), $len, self::BASE == 64 ? self::$BASE64[0] : "0", STR_PAD_LEFT);
        }

        static private function generate_checksum($id) {
            $check = self::str_baseconvert(abs(crc32($id)), 10, self::BASE);
            $check = self::make_length($check, self::CHECK_LENGTH);

            return $check;
        }

        static public function generate_random_id($prefix = '') {
            $md5 = md5(microtime(false));
            $id = self::make_length(self::str_baseconvert($md5, 16, self::BASE), self::ID_LENGTH);

            if ($prefix) {
                $id = $prefix . '-' . $id;
            }

            return $id . self::generate_checksum($id);
        }

        static public function validate_id($id) {
            // does id without prefix have correct length?
            $length = self::ID_LENGTH + self::CHECK_LENGTH;
            if (!preg_match("!^(.*\-)?[A-Za-z0-9_\-]{{$length}}$!", $id)) {
                return false;
            }

            $check = substr($id, -self::CHECK_LENGTH);
            $id = substr($id, 0, -self::CHECK_LENGTH);

            return self::generate_checksum($id) === $check;
        }
    }

}
