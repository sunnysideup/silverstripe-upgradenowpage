<?php

class UpgradeNowPage_DO extends DataObject{

    static $db = array(
        "Name" => "Varchar",
        "Email" => "Varchar",
        "UpgradeTo" => "Varchar",
        "TechnologiesUsed" => 'MultiEnum("ftp,svn,git,composer,mercurial,other,none","")',
        "WebsiteAddress" => "Varchar(255)",
        "CurrentlyInstalledModules" => "Text",
        "Quote" => "HTMLText",
        "Notes" => "Text",
        "Code" => "Varchar(10)",
    );

    static $has_one = array(
        "MysiteZip" => "File",
    );

    static $summary_fields = array(
        "Name" => "Name",
        "Email" => "Email",
        "Created" => "Created"
    );

    static $default_sort = "Created DESC";

    function onBeforeDelete(){
        parent::onBeforeDelete();
        $array = array(
            $this->fullPathDownloadFileLocation(),
            $this->fullPathOriginalFolderLocation()
        );
        foreach($array as $location) {
            $this->deletePath($location);
        }
        Filesystem::sync();
    }

    private function deletePath($path) {
        if(file_exists($path)) {
            if (is_dir($path)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $file) {
                    if ($file->isDir()){
                        rmdir($file->getPathname());
                    }
                    else{
                        unlink($file->getPathname());
                    }
                }
                rmdir($path);
            }
            else {
                unlink($path);
            }
        }
    }

    function downloadFileLocation(){
        return "/assets/downloads/".$this->Code.".zip";
    }

    function fullPathDownloadFileLocation(){
        return Director::baseFolder().$this->downloadFileLocation();
    }

    function originalFolderLocation(){
        return "/assets/upgraders/".$this->Code."/";
    }

    function fullPathOriginalFolderLocation(){
        return Director::baseFolder().$this->originalFolderLocation();
    }

}
