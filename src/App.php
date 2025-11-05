<?php

class App extends \atk4\ui\App
{
    public $db;
    public $sms;

    function __construct($mode)
    {
        parent::__construct("Vecāku diena");
        error_reporting(E_ALL & ~E_DEPRECATED);

        if ($mode == "public") {
            $this->initLayout("Centered");
            $this->includeCustomAssets();

            $this->layout->template->del("Header");

            $logo = "logo.png";

            $this->layout->add(["Image", $logo, "small centered"], "Header");
            //$this->layout->add(['Label','Work','red right'],'Header');

            $this->layout->add(
                [
                    "Header",
                    "Vecāku diena",
                    "size" => "huge",
                    "aligned" => "center",
                ],
                "Header",
            );
        } elseif ($mode == "admin") {
            $this->initLayout("Admin");
            $this->includeCustomAssets();
            $this->layout->leftMenu->addItem(
                ["Galvenā lapa", "icon" => "home"],
                ["logout"],
            );
            $this->layout->leftMenu->addItem(
                ["Priekšmeti", "icon" => "book"],
                ["admin", "check" => "lessons"],
            );
            $this->layout->leftMenu->addItem(
                ["Skolotāji", "icon" => "users"],
                ["admin", "check" => "teachers"],
            );
            $this->layout->leftMenu->addItem(
                ["Ieraksti", "icon" => "unordered list"],
                ["admin"],
            );
            $this->layout->leftMenu->addItem(
                ["Virsraksti", "icon" => "pencil alternate"],
                ["admin", "check" => "text"],
            );
        } elseif ($mode == "print") {
            $this->initLayout("Centered");
            $this->includeCustomAssets();

            $this->layout->template->del("Header");
        }
        if (isset($_ENV["DATABASE_URL"])) {
            $this->db = \atk4\data\Persistence::connect($_ENV["DATABASE_URL"]);
        } else {
            $this->db = \atk4\data\Persistence::connect(
                "mysql:host=localhost;dbname=testdb",
                "root",
                "rootpassword",
            );
        }
    }

    private function includeCustomAssets(): void
    {
        $assetPath = "assets/css/app.css";
        if ($this->html && file_exists($assetPath)) {
            $version = filemtime($assetPath);
            $this->html->template->appendHTML(
                "HEAD",
                sprintf('<link rel="stylesheet" href="%s?v=%s">', $assetPath, $version),
            );
        }
    }
}
// 'mysql:dbname=vecaku-diena;unix_socket=/cloudsql/project:region:instance,root,vQtu~vDOoqN%$<Y5'
