define([], function () {
    window.requirejs.config({
        paths: {
            "jquery.touchpunch": M.cfg.wwwroot + '/blocks/exaport/javascript/jquery.ui.touch-punch',
        },
        shim: {
            'jquery.touchpunch': {
                deps: ['jquery', 'jqueryui'],
                exports: '$'
            },
        }
    });
});