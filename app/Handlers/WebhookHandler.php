<?php

namespace App\Handlers;

use App\TelegramBot;
use function App\handleCommand;

class WebhookHandler
{
    public function handle(): void
    {
        $bot = new TelegramBot();
        $update = json_decode(file_get_contents('php://input'), true);

        if ($update && isset($update['message'])) {
            $userChatId = $update['message']['from']['id'];
            $userMessage = $update['message']['text'] ?? "nothing";
            $firstName = $update['message']['from']['first_name'] ?? "N/A";
            $lastName = $update['message']['from']['last_name'] ?? "N/A";
            $fullName = $lastName === "N/A" ? $firstName : "$firstName $lastName";

            $replyMsg = handleCommand($userMessage, $fullName);

            $bot->sendRequest('sendMessage', [
                'chat_id' => $userChatId,
                'text' => $replyMsg,
                'parse_mode' => 'html',
            ]);
        }
    }
}
