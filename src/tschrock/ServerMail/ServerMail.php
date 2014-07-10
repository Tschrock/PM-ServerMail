<?php

/**
 * XPerience - an XP framework for your pocketmine server.
 * 
 * @author Tschrock <tschrock@gmail.com>
 * @link http://www.tschrock.net
 */

namespace tschrock\ServerMail;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use tschrock\ServerMailAPI;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;

/**
 * The main plugin class.
 */
class ServerMail extends PluginBase implements Listener {
    
    const CONFIG_MAXMESSAGE = "maxMessagesToPlayer";
    const CONFIG_SIMILARLIM = "similarLimit";

    /**
     * The onLoad function - empty.
     */
    public function onLoad() {
        
    }

    /**
     * The onEnable function - just setting up the config.
     */
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        #$this->saveDefaultConfig();
        #$this->reloadConfig();

        ServerMailAPI::setupDataFiles($this);

        $reflector = new \ReflectionClass('\tschrock\ServerMailAPI');
        $this->getLogger()->info("Using ServerMailAPI found at '" . $reflector->getFileName() . "'");
    }

    /**
     * The onDisable function - also empty.
     */
    public function onDisable() {
        
    }

    /**
     * The command handler - Handles user input for the /mail command.
     * 
     * @param \pocketmine\command\CommandSender $sender The person who sent the command.
     * @param \pocketmine\command\Command $command The command.
     * @param string $label The label for the command. - What's this?
     * @param array $args The arguments with the command.
     * @return boolean Wether or not the command succeded.
     */
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch ($command->getName()) {
            case "mail":
                switch (strtolower(array_shift($args))) {
                    case "read":  // fallthrough
                    case "view":
                        $messages = ServerMailAPI::getMessages($this->getUserName($sender));
                        $this->sendChat($sender, "[ServerMail] You have " . count($messages) . " messages:");
                        foreach ($messages as $message) {
                            $this->sendChat($sender, "    " . $message["sender"] . ": " . $message["message"]);
                        }
                        break;
                    case "clear":
                        ServerMailAPI::clearMessages($this->getUserName($sender));
                        $this->sendChat($sender, "[ServerMail] All messages cleared");
                        break;
                    case "send":
                        $senderName = $this->getUserName($sender);
                        $recipiant = strtolower(array_shift($args));
                        $message = implode(" ", $args);

                        if ($recipiant != NULL && $message != NULL) {
                            if ($this->checkUser($recipiant)) {

                                if ($this->isMessageSimilar($senderName, $recipiant, $message)) {
                                    $this->sendChat($sender, "[ServerMail] You already sent a message like that!");
                                } else {
                                    $msgCount = ServerMailAPI::countMessagesFromPlayer($senderName, $recipiant);
                                    $msgCountMax = $this->getConfig()->get(ServerMail::CONFIG_MAXMESSAGE);
                                    if ($msgCount > $msgCountMax) {
                                        $this->sendChat($sender, "[ServerMail] You have reached your message limit to $recipiant! (" . ($msgCount - 1) . "/$msgCountMax)");
                                    } else {
                                        ServerMailAPI::addMessage($recipiant, $senderName, $message);
                                        $this->sendChat($sender, "[ServerMail] Message sent! ($msgCount/$msgCountMax)");
                                    }
                                }
                            } else {
                                $this->sendChat($sender, "[ServerMail] $recipiant has no mailbox!");
                            }
                        } else {
                            $this->sendChat($sender, "Usage: /mail send <player> <message>");
                        }

                        break;
                    case "broadcast":

                    //break;
                    default:
                        $this->sendChat($sender, "Usage: /mail <view|read|clear|send>");
                }
                return true;
            default:
                return false;
        }
    }

    public function checkUser($name) {
        $name = strtolower($name);
        return file_exists($this->getServer()->getDataPath() . "players/$name.dat");
    }

    public function isMessageSimilar($fromPlayer, $toPlayer, $newmessage) {

        $limit = $this->getConfig()->get(ServerMail::CONFIG_SIMILARLIM);

        #console("limit:$limit");
        #console("1 - limit:" . 1 - $limit);

        if ($limit == 0) {
            return false;
        }

        $messages = ServerMailAPI::getMessages($toPlayer);
        foreach ($messages as $message) {
            if ($message["sender"] == $fromPlayer) {

                if ($this->compareStrings($message["message"], $newmessage) <= (1 - $limit)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function compareStrings($str1, $str2) {
        $str1m = metaphone($str1);
        $str2m = metaphone($str2);

        #console("str1m:$str1m");
        #console("str2m:$str2m");

        $dist = levenshtein($str1m, $str2m);

        #console("dist:$dist");
        #console("return:" . $dist / max(strlen($str1m), strlen($str2m)));

        return $dist / max(strlen($str1m), strlen($str2m));
    }

    public function sendChat(CommandSender $to, $message) {
            $to->sendMessage($message);
    }

    public function getUserName($issuer) {
        if ($issuer instanceof \pocketmine\Player) {
            return $issuer->getName();
        } else {
            return "Server";
        }
    }
    
    
    /**
     * @param PlayerRespawnEvent $event
     *
     * @priority NORMAL
     * @ignoreCancelled false
     */
    public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        
        $messagecount = ServerMailAPI::getMessageCount($player);

                if ($messagecount == 0) {
                    $player->sendMessage("[ServerMail] You have no messages.");
                } else {
                    $player->sendMessage("[ServerMail] You have " . $messagecount . " messages.");
                    $player->sendMessage("Use '/mail read' to see them. ");
                }
    }
    
    
    

}
