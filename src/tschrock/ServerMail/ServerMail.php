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
use pocketmine\utils\Config;

/**
 * The main plugin class.
 */
class ServerMail extends PluginBase implements Listener {

    const CONFIG_MAXMESSAGE = "maxMessagesToPlayer";
    const CONFIG_SIMILARLIM = "similarLimit";
    const CONFIG_NOTIFY = "notifyOnNew";

    /** @var string[] */
    protected $messages = [];

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
        $this->saveDefaultConfig();
        $this->reloadConfig();


        $this->saveResource("messages.yml", false);
        $messages = (new Config($this->getDataFolder() . "messages.yml"))->getAll();
        $this->messages = $this->parseMessages($messages);

        $mailCommand = $this->getCommand("mail");
        $mailCommand->setAliases(array($this->getMessage("commands.names.mail")));
        $mailCommand->setDescription($this->getMessage("commands.description"));
        $mailCommand->setUsage($this->getMainCommandUsage());


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
            case $this->getMessage("commands.names.mail"):
                switch (strtolower(array_shift($args))) {
                    case "read":
                    case $this->getMessage("commands.names.read"):
                        $messages = ServerMailAPI::getMessages($this->getUserName($sender));
                        $sender->sendMessage("[ServerMail] " . sprintf($this->getMessage("messages.count"), count($messages)) . ".");
                        foreach ($messages as $message) {
                            $sender->sendMessage("    " . $message["sender"] . ": " . $message["message"]);
                        }
                        break;
                    case "clear":
                    case $this->getMessage("commands.names.clear"):
                        ServerMailAPI::clearMessages($this->getUserName($sender));
                        $sender->sendMessage("[ServerMail] " . $this->getMessage("messages.cleared"));
                        break;
                    case "send":
                    case $this->getMessage("commands.names.send"):
                        $senderName = $this->getUserName($sender);
                        $recipiant = strtolower(array_shift($args));
                        $message = implode(" ", $args);

                        if ($recipiant != NULL && $message != NULL) {
                            if ($this->checkUser($recipiant)) {

                                if ($this->isMessageSimilar($senderName, $recipiant, $message)) {
                                    $sender->sendMessage($this->getMessage("messages.similar"));
                                } else {
                                    $msgCount = ServerMailAPI::countMessagesFromPlayer($senderName, $recipiant);
                                    $msgCountMax = $this->getConfig()->get(ServerMail::CONFIG_MAXMESSAGE);
                                    if ($msgCount > $msgCountMax) {
                                        $sender->sendMessage("[ServerMail] " . sprintf($this->getMessage("messages.too_many"), $recipiant) . " (" . ($msgCount - 1) . "/$msgCountMax)");
                                    } else {
                                        ServerMailAPI::addMessage($recipiant, $senderName, $message);
                                        $sender->sendMessage("[ServerMail] " . $this->getMessage("messages.sent") . " ($msgCount/$msgCountMax)");
                                        $this->sendNotification($recipiant, $senderName);
                                    }
                                }
                            } else {
                                $sender->sendMessage("[ServerMail] " . sprintf($this->getMessage("messages.no_player"), $recipiant));
                            }
                        } else {
                            $sender->sendMessage($this->getSendCommandUsage());
                        }

                        break;
                    case "sendall":
                    case $this->getMessage("commands.names.sendall"):
                        if ($sender->hasPermission("tschrock.servermail.command.mail.all")) {
                            $senderName = $this->getUserName($sender);
                            $message = implode(" ", $args);
                            ServerMailAPI::sendall($senderName, $message);
                            $sender->sendMessage("[ServerMail] " . $this->getMessage("messages.sent"));
                            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                                $this->sendNotification($player->getName(), $senderName);
                            }
                        } else {
                            $sender->sendMessage("[ServerMail] " . $this->getMessage("messages.not_allowed"));
                        }
                        break;
                    default:
                        $sender->sendMessage($this->getMessage("commands.usage.usage") . ": " . $this->getMainCommandUsage());
                }
                return true;
            default:
                return false;
        }
    }

    public function checkUser($name) {
        $name = strtolower($name);
        return file_exists($this->getServer()->getDataPath() . "players/$name.dat") || $name == "server";
    }

    public function sendNotification($player, $sender) {
        if ($this->getConfig()->get(ServerMail::CONFIG_NOTIFY) &&
                ($pPlayer = $this->getServer()->getPlayerExact($player)) !== null &&
                $pPlayer->isOnline()) {
            $pPlayer->sendMessage("[ServerMail] " . sprintf($this->getMessage("messages.new_message"), $sender));
        }
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

        $player->sendMessage("[ServerMail] " . sprintf($this->getMessage("messages.count"), $messagecount) . ".  /"
                . $this->getMessage("commands.names.mail") . " "
                . $this->getMessage("commands.names.read"));
    }

    public function getMessage($key) {
        return isset($this->messages[$key]) ? $this->messages[$key] : $key;
    }

    public function getMainCommandUsage() {
        return "/" . $this->getMessage("commands.names.mail")
                . " < " . $this->getMessage("commands.names.read") . " | "
                . $this->getMessage("commands.names.clear") . " | "
                . $this->getMessage("commands.names.send") . " | "
                . $this->getMessage("commands.names.sendall") . " >";
    }

    public function getSendCommandUsage() {
        return $this->getMessage("commands.usage.usage") . ": /"
                . $this->getMessage("commands.names.mail") . " "
                . $this->getMessage("commands.names.send") . " < "
                . $this->getMessage("commands.usage.player") . " > < "
                . $this->getMessage("commands.usage.message") . " >";
    }

    private function parseMessages(array $messages) {
        $result = [];
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                foreach ($this->parseMessages($value) as $k => $v) {
                    $result[$key . "." . $k] = $v;
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

}
