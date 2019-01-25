<?php

class UpgradeNowPage extends Page {

    private static $icon = "mysite/images/treeicons/UpgradeNowPage";

    private static $db = array(
        "ThankYouContent" => "HTMLText"
    );

    public function canCreate($member = null) {
        return UpgradeNowPage::get()->First() == null;
    }

    function getCMSFields(){
        $fields = parent::getCMSFields();
        $fields->addFieldToTab("Root.ThankYou", new HtmlEditorField("ThankYouContent"));
        return $fields;
    }

}
