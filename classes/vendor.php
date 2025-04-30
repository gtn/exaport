<?php

namespace block_exaport;

class vendor {
    static function load() {
        require_once 'phar://' . __DIR__ . '/../build/vendor.phar';
    }
}
