<?php

namespace App\Handlers;

use App\TelegramBot;
use function App\handleCommand;

class PollingHandler
{
    public function handle(): void
    {
        $bot = new TelegramBot();
        $lastUpdateId = 0;

        while (true) {
            $response = $bot->sendRequest('getUpdates', [
                'offset' => $lastUpdateId + 1,
                'timeout' => 30,
            ]);

            if (isset($response['result'])) {
                foreach ($response['result'] as $update) {
                    $lastUpdateId = $update['update_id'];
                    $this->handleUpdate($update, $bot);
                }
            }

            usleep(500000); // 0.5 seconds delay
        }
    }

    private function handleUpdate(array $update, TelegramBot $bot): void
    {
        if (isset($update['message'])) {
            $userChatId = $update['message']['from']['id'];
            $userMessage = $update['message']['text'] ?? "nothing";
            $firstName = $update['message']['from']['first_name'] ?? "N/A";
            $lastName = $update['message']['from']['last_name'] ?? "N/A";
            $fullName = $lastName === "N/A" ? $firstName : "$firstName $lastName";

            $replyMsg = handleCommand($userMessage, $fullName);

            $bot->sendRequest('sendMessage', [
                'chat_id' => $userChatId,
                'text' => $replyMsg,
                'parse_mode' => 'html'
            ]);
        }
    }
}
