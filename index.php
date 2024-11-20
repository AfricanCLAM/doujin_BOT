<?php
// Include the necessary files
include 'commands.php';
include 'telegram_api.php';

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

//  $mode = 'webhook';
$mode = 'longpolling';

if ($mode === 'webhook') {

    $update = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents('./log/debug.log', "JSON Decode Error: " . json_last_error_msg() . "\n", FILE_APPEND);
    }
    $userChatId = isset($update["message"]["from"]["id"]) ? $update["message"]["from"]["id"] : null;

    if ($userChatId) {
        $userMessage = isset($update['message']['text']) ? $update['message']['text'] : "nothing"; // Check if text is set
        $firstName = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : "N/A";
        $lastName = isset($update['message']['from']['last_name']) ? $update['message']['from']['last_name'] : "N/A";

        // Condition to check if the last name is not "N/A"
        $fullName = $lastName === "N/A" ? $firstName : $firstName . " " . $lastName;

        // Use the handleCommand function to get the reply message
        $replyMsg = handleCommand($userMessage, $fullName);

        $parameters = array(
            "chat_id"    => $userChatId,
            "text"       => $replyMsg,
            "parse_mode" => "html"
        );

        /*DEBUG*/
        // echo "chat id: $parameters['chat_id']/n";
        // echo "$bot reply :parameters['text']/n";
        // echo 'bot mode: $mode'/n;
        /*DEBUG END*/

        send_webhook("sendMessage", $parameters);
    }
} elseif ($mode === 'longpolling') {
    // Function to handle incoming messages
    function handleUpdate($update)
    {
        file_put_contents('./log/debug.log', "Update received: " . json_encode($update) . "\n\n", FILE_APPEND);

        if (isset($update['message'])) {
            $userChatId = $update['message']['from']['id'];
            $userMessage = $update['message']['text'] ?? "nothing";
            $firstName = $update['message']['from']['first_name'] ?? "N/A";
            $lastName = $update['message']['from']['last_name'] ?? "N/A";
            $fullName = $lastName === "N/A" ? $firstName : "$firstName $lastName";

            $replyMsg = handleCommand($userMessage, $fullName);

            send_polling("sendMessage", [
                "chat_id" => $userChatId,
                "text" => $replyMsg
            ]);
        }
    }


    // Long polling loop
    $lastUpdateId = 0;
    while (true) {
        $response = send_polling("getUpdates", [
            "offset" => $lastUpdateId + 1,
            "timeout" => 30 // Long polling timeout in seconds
        ]);

        if (isset($response['result'])) {
            foreach ($response['result'] as $update) {
                $lastUpdateId = $update['update_id'];
                handleUpdate($update);
            }
        }

        // Optional: Add a short delay to avoid hitting API limits
        usleep(500000); // 0.5 seconds
    }
}
