<?php


class UpgradeNowPage_Validator extends RequiredFields {
    /**
     * Ensures member unique id stays unique and other basic stuff...
     * @param array $data = Form Data
     * @return Boolean
     */
    function php($data){
        $valid = parent::php($data);

        if(strtolower($data["WhatIsTheCapitalOfNewZealand"]) != "wellington") {
        //$this->form->addErrorMessage("WhatIsTheCapitalOfNewZealand", "please complete our spam test correctly", "bad");
        //$this->form->redirectBack()		"MysiteZip" => "File",
            $this->validationError(
                "WhatIsTheCapitalOfNewZealand",
                "please complete our spam test correctly",
                "bad"
            );
            $valid = false;
        }
        return $valid;
    }

}
