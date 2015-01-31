<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace tschrock;

use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;

/**
 * Description of ServerMailAPI
 *
 * @author tyler
 */
class ServerMailAPI {

    private static $isSetup = false;
    private static $config = false;
    private static $dataDir = false;

    const CONFIG_NPMESSAGE = "newPlayerMessage";

    public static function setupDataFiles(Plugin $plugin) {
        if (self::$isSetup === false) {
            self::$dataDir = $plugin->getServer()->getPluginPath() . "ServerMail/";
            @mkdir(self::$dataDir, 0777, true);
            self::$config = new Config(self::$dataDir . "config.yml", Config::YAML, array(
                self::CONFIG_NPMESSAGE => "Welcome to the Server!",
            ));
            self::$isSetup = true;
        }
    }

    public static function getMessageCount($player) {
        return count(self::getMessages($player));
    }

    public static function getMessages($player) {
        $d = self::getData($player);
        if ($d === false) {
            return false;
        }
        $m = $d->get("messages");
        if ($d->get("firstrun")) {
            $m[] = array(
                "time" => time(),
                "sender" => "Server",
                "message" => self::$config->get(self::CONFIG_NPMESSAGE),
            );
        }
        return $m;
    }

    public static function addMessage($player, $sender, $message) {
        $d = self::getData($player);
        if ($d === false) {
            return false;
        }
        $e = $d->get("messages");
        $e[] = array(
            "time" => time(),
            "sender" => "$sender",
            "message" => "$message",
        );
        $d->set("messages", $e);
        $d->save();
    }

    public static function clearMessages($player) {
        $d = self::getData($player);
        if ($d === false) {
            return false;
        }
        $d->remove("messages");
        $d->set("firstrun", false);
        $d->save();
    }

    public static function getData($player) {
        if ($player instanceof \pocketmine\Player) {
            $iusername = $player->getName();
        } elseif (is_string($player)) {
            $iusername = $player;
        } else {
            return false;
        }

        $iusername = strtolower($iusername);
        if (!file_exists(self::$dataDir . "players/" . $iusername{0} . "/$iusername.yml")) {
            @mkdir(self::$dataDir . "players/" . $iusername{0} . "/", 0777, true);
            $d = new Config(self::$dataDir . "players/" . $iusername{0} . "/" . $iusername . ".yml", Config::YAML, array(
                "firstrun" => true,
                "messages" => array(),
            ));

            $d->save();
            return $d;
        }
        return new Config(self::$dataDir . "players/" . $iusername{0} . "/" . $iusername . ".yml", Config::YAML, array(
            "firstrun" => true,
            "messages" => array(),
        ));
    }

    public static function countMessagesFromPlayer($fromPlayer, $toPlayer) {
        $mcount = 0;
        $messages = self::getMessages($toPlayer);
        foreach ($messages as $message) {
            if ($message["sender"] == $fromPlayer) {
                $mcount++;
            }
        }
        return $mcount;
    }

    public static function sendall($sender, $message) {
        $directory_iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$dataDir . "players/"));
        foreach ($directory_iterator as $filename => $path_object) {
            if (stripos(strrev($filename), "lmy.") === 0) {
                self::addMessage(basename($filename, ".yml"), $sender, $message);
            }
        }
    }

}
