<?php

class UpgradeNowPage_Controller extends Page_Controller {

    private static $allowed_actions = array(
        "thankyou",
        "deletedownload",
        "requestquote",
        "download",
        "Form"
    );

    function init(){
        increase_time_limit_to(600);
        parent::init();
    }

    private $showForm = true;

    function Form() {
        $parentFolder = Folder::find_or_make("upgraders");
        $this->readProtectWitHTACCESS($parentFolder->getFullPath());
        if($this->showForm) {
            $fields = new FieldList();
            $fields->push( new TextField("Name"));
            $fields->push( new EmailField("Email"));
            $fields->push( new TextField("WebsiteAddress", "Where can we view the live site (optional)"));
            $fields->push( new CheckboxSetField("TechnologiesUsed", "Technologies used to (version) manage this site", array("ftp" => "FTP", "svn" => "SVN", "git" => "GIT", "mercurial" => "Mercurial", "composer" => "Composer", "other" => "Other", "none" => "None")));
            $fields->push( new OptionsetField("UpgradeTo", "I want to upgrade to ...", array("3.0" => "3.0 (current code must work in silverstripe 2.4)", "3.1" => "3.1 (current code must work in silverstripe 3.0)")));
            $fields->push( new TextareaField("CurrentlyInstalledModules", "Currently Installed Modules (e.g. userforms, DataObjectManager) that you dont need to update yourself.  For these we can provide a recommended version."));
            $fields->push( $file1 = new FileField('MysiteZip', "Zipped folder (e.g. mysite.zip) of your mysite + theme folder + other custom module folders.  MAX SIZE: 2 Megabytes (we recommend removing any graphics to reduce size)", $value = null, $form = null, $rightTitle = null, $folderName = "upgraders"));
            $fields->push( new TextareaField('Notes', "Notes or questions"));
            $fields->push( new Textfield('WhatIsTheCapitalOfNewZealand', "What is the capital of New Zealand"));
            $actions = new FieldList(
                new FormAction('requestquote', 'Request Quote')
            );
            $validator = new UpgradeNowPage_Validator(array('Name', 'Email', 'UpgradeTo', 'MysiteZip'));
            $form =  new Form($this, 'Form', $fields, $actions, $validator);
            $file1->getValidator()->setAllowedExtensions(array('zip')); //,'tar', 'bz2', 'gz','rar'
            $file1->getValidator()->setAllowedMaxFileSize(array("*" => 2097152));
            return $form;
        }
    }

    function thankyou($request){
        $this->Content = $this->ThankYouContent;
        if($request->param("ID") == Session::get("QuoteID")) {
            $this->showForm = false;
            $obj = UpgradeNowPage_DO::get()->byID(intval($request->param("ID")));
            $this->Content .= $obj->Quote;
        }
        return array();
    }

        //getAbsoluteURL
        //getFullPath

    function requestquote($data, $form){
        //create parent folder
        $parentFolder = Folder::find_or_make("upgraders");
        $this->readProtectWitHTACCESS($parentFolder->getFullPath());
        //create child folder
        $folderName = $this->generateRandomString();
        $folder = Folder::find_or_make("/upgraders/".$folderName);
        $folder->setParentID($parentFolder->ID);
        $folder->write();
        $folder->flushCache();
        //create upgrade object
        $obj = new UpgradeNowPage_DO();
        $form->saveInto($obj);
        $obj->Code = $folder->Name;
        $obj->write();
        //notify me
        mail("nfrancken@gmail.com", "new upgrade request", $obj->ID);
        if($obj->MysiteZipID) {
            $file = $obj->MysiteZip();
            $file->setParentID($folder->ID);
            $file->write();
            $file->flushCache();
            $this->unzip($folder->getFullPath(), $file->getFullPath());
            require_once(Director::baseFolder()."/upgrade/UpgradeSilverstripe.php");
            $upgrader = new UpgradeSilverstripe();
            $upgradeDetails = $upgrader->run(
                $folder->getFullPath(),
                $logFileLocation = $folder->getFullPath()."/ss_upgrade_log.txt",
                $to = $obj->UpgradeTo,
                $doBasicReplacement = true,
                $markStickingPoints = true,
                //Adds blog and userforms as additional folders to ignore.
                $ignoreFolderArray = array()
            );
            $upgradeDetails = str_replace('<%', '', $upgradeDetails);
            $upgradeDetails = str_replace('$', '', $upgradeDetails);
            $cost = ($upgrader->getNumberOfStraightReplacements() * 1 )+($upgrader->getNumberOfAllReplacements() * 10);
            $downloadLink = $this->createDownload($folder, $obj);
            $deleteDownloadLink = $this->Link("deletedownload/".$obj->ID."/");
            $obj->Quote ="
                <h2>Upgrade Details</h2>
                <h3>Download Upgraded Code</h3>
                <p><a href=\"$downloadLink\">Download basic upgrade of your files now</a>!<br />(in case you are not interested, please <a href=\"$deleteDownloadLink\">delete the downloadable file</a>).</p>
                <h3>Upgrade Details</h3>
                ".$upgradeDetails."
                <h2>Quote</h2>
                <p>Estimated cost to finalise upgrade of the uploaded folder to the ".$obj->UpgradeTo." version of the Silverstripe CMS: NZD".$cost.".00, please contact us if you are interested in pursuing this option (upgrade @ silverstripe.co.nz).</p>
            ";
            $obj->write();
        }
        else {
            $obj->Quote ="No quote can be provided without zip file...";
            $obj->write();
        }
        Session::set("QuoteID", $obj->ID);
        $this->redirect($this->Link("thankyou/".$obj->ID."/"));
        return;
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    private function unzip($fullFolderPath, $fullFilePath){
        if (!extension_loaded('zip')) {
            user_error("Zip extension not loaded");
        }
        $zip = zip_open($fullFilePath);
        if ($zip) {
            while ($zip_entry = zip_read($zip)) {
                $zipEntryFileLocation = zip_entry_name($zip_entry);
                $path = $fullFolderPath.dirname($zipEntryFileLocation);
                if(strpos($path,"/.")) {
                    continue;
                }
                if(strpos($path,"../")) {
                    continue;
                }
                $zipEntryFileInfo = pathinfo($zipEntryFileLocation);
                if(!isset($zipEntryFileInfo['extension'])) {
                    continue;
                }
                $extension = $zipEntryFileInfo['extension'];
                if(!in_array($extension, array("php", "ss", "yaml", "css", "js", "yml"))) {
                    continue;
                }
                if(!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $fp = fopen($fullFolderPath.$zipEntryFileLocation, "w");
                if (zip_entry_open($zip, $zip_entry, "r")) {
                    $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                    fwrite($fp,"$buf");
                    zip_entry_close($zip_entry);
                    fclose($fp);
                }
            }
        }
        zip_close($zip);
    }

    private function readProtectWitHTACCESS($fullPath) {
        $fileName = $fullPath.".htaccess";
        if(!file_exists($fileName)) {
            $content = <<<text
order deny,allow
deny from all
text;
            $handle = fopen($fileName, "w");
            $numbytes = fwrite($handle, $content);
            fclose($handle);
        }
    }


    private function createDownload(Folder $folder, $obj) {
        $folderLocationFullPath = $folder->getFullPath();
        $folderName = $folder->Name;
        $zipFileName = $obj->downloadFileLocation();
        $zipFileNameFullPath = $obj->fullPathDownloadFileLocation();
        $this->createZip($folderLocationFullPath,$zipFileNameFullPath);
        /*
        exec('
            cd '.$folderLocationFullPath.'
            zip -r '.$zipFileNameFullPath.' .  -x "*.svn/*" -x "*.git/*"'
        );
        if(!file_exists($zipFileNameFullPath)) {
            user_error("could not find $zipFileNameFullPath");
        }
        * */
        if(!file_exists($zipFileNameFullPath)) {
            user_error("Could not create zip file: $zipFileNameFullPath");
        }
        return $this->Link("download/".$obj->ID."/");
    }


    public function deletedownload($request){
        $id = intval($request->param("ID"));
        if($request->param("ID") == Session::get("QuoteID")) {
            $obj = UpgradeNowPage_DO::get()->byID(intval($request->param("ID")));
            $fullZipFileLocation = $obj->fullPathDownloadFileLocation();
            if(file_exists($fullZipFileLocation)) {
                unlink($fullZipFileLocation);
                $this->Content = "<h2>SUCCESS: Download file deleted.</h2>";
            }
            else {
                $this->Content = "<h2>ERROR: Could not delete file!</h2>";
            }
        }
        return array();
    }



    function download($request){
        $id = intval($request->param("ID"));
        if($request->param("ID") == Session::get("QuoteID")) {
            $obj = UpgradeNowPage_DO::get()->byID(intval($request->param("ID")));
            $file = $obj->Code;
            $fullLocation = $obj->fullPathDownloadFileLocation();
            if(file_exists($fullLocation)){
                return SS_HTTPRequest::send_file(file_get_contents($fullLocation), $file.".zip");
            }
            else {
                $this->Content = "<h3>Download file could not be found $fullLocation</h3>";
                $this->showForm = false;
            }
        }
        else {
            $this->Content = "<h3>Invalid download</h3>";
            $this->showForm = false;
        }
        return array();
    }



    private function createZip($source, $destination){
        if (!extension_loaded('zip')) {
            user_error("Zip extension not loaded");
        }
        if(!file_exists($source)) {
            user_error("could not find zip file: $source");
        }

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file){
                $file = str_replace('\\', '/', realpath($file));
                if (is_dir($file) === true){
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                }
                else if (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        }
        else if (is_file($source) === true){
            $zip->addFromString(basename($source), file_get_contents($source));
        }
        return $zip->close();
    }

    private function deleteAll($directory, $empty = false) {
        if(substr($directory,-1) == "/") {
            $directory = substr($directory,0,-1);
        }

        if(!file_exists($directory) || !is_dir($directory)) {
            return false;
        }
        elseif(!is_readable($directory)) {
            return false;
        }
        else {
            $directoryHandle = opendir($directory);
            while ($contents = readdir($directoryHandle)) {
                if($contents != '.' && $contents != '..') {
                    $path = $directory . "/" . $contents;
                    if(is_dir($path)) {
                        deleteAll($path);
                    }
                    else {
                        unlink($path);
                    }
                }
            }
            closedir($directoryHandle);
            if($empty == false) {
                if(!rmdir($directory)) {
                    return false;
                }
            }
            return true;
        }
    }
}
