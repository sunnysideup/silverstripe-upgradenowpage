<?php


class UpgradeNowPage_Admin extends ModelAdmin{

    private static $url_segment = 'upgrades';

    private static $menu_title = 'upgrade requests';


    /**
     * Change this variable if you don't want the Import from CSV form to appear.
     * This variable can be a boolean or an array.
     * If array, you can list className you want the form to appear on. i.e. array('myClassOne','myClasstwo')
     */
    public $showImportForm = false;

    private static $menu_icon = "";

    private static $managed_models = array(
        "UpgradeNowPage_DO"
    );

    function urlSegmenter() {
        return self::$url_segment;
    }

}
