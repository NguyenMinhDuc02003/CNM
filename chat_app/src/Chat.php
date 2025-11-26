<?php

//Chat.php

namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require dirname(__DIR__) . "/database/ChatUser.php";
require dirname(__DIR__) . "/database/ChatRooms.php";
require dirname(__DIR__) . "/database/PrivateChat.php";

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $logFile;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->logFile = dirname(__DIR__) . '/logs/chat-debug.log';
        $logDirectory = dirname($this->logFile);
        if(!is_dir($logDirectory))
        {
            @mkdir($logDirectory, 0777, true);
        }
        $this->logDebug('Server bootstrapped and ready to accept connections');
        echo 'Server Started';
    }

    public function onOpen(ConnectionInterface $conn) {

        // Store the new connection to send messages to later
        echo 'Server Started';

        $this->clients->attach($conn);

        $querystring = $conn->httpRequest->getUri()->getQuery();

        parse_str($querystring, $queryarray);

        $this->logDebug('Incoming connection', [
            'resource_id' => $conn->resourceId,
            'query' => $queryarray
        ]);

        if(isset($queryarray['token']))
        {

            $user_object = new \ChatUser;

            $user_object->setUserToken($queryarray['token']);

            $user_object->setUserConnectionId($conn->resourceId);

            $user_object->update_user_connection_id();

            $user_data = $user_object->get_user_id_from_token();
            
            $user_id = isset($user_data['user_id']) ? $user_data['user_id'] : null;

            if($user_id)
            {
                $data['status_type'] = 'Online';

                $data['user_id_status'] = $user_id;

                $this->logDebug('User marked online', [
                    'user_id' => $user_id,
                    'resource_id' => $conn->resourceId
                ]);

                // first, you are sending to all existing users message of 'new'
                foreach ($this->clients as $client)
                {
                    $client->send(json_encode($data)); //here we are sending a status-message
                }
            }
            else
            {
                $this->logDebug('Token resolved but no user_id returned', [
                    'token' => $queryarray['token']
                ]);
            }
        }
        else
        {
            $this->logDebug('Connection missing token, skipping presence update', [
                'resource_id' => $conn->resourceId
            ]);
        }

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        $data = json_decode($msg, true);
        if(!is_array($data))
        {
            $this->logDebug('Received invalid JSON payload', [
                'resource_id' => $from->resourceId,
                'raw' => $msg
            ]);
            return;
        }

        $command = isset($data['command']) ? $data['command'] : 'group';
        $attachment = isset($data['attachment']) && is_array($data['attachment']) ? $data['attachment'] : null;

        $this->logDebug('Received message', [
            'resource_id' => $from->resourceId,
            'command' => $command,
            'userId' => $data['userId'] ?? null,
            'receiver_userid' => $data['receiver_userid'] ?? null
        ]);

        if($command == 'private')
        {
            //private chat

            $private_chat_object = new \PrivateChat;

            $private_chat_object->setToUserId($data['receiver_userid']);

            $private_chat_object->setFromUserId($data['userId']);

            if(!empty($data['conversation_id']))
            {
                $private_chat_object->setConversationId((int)$data['conversation_id']);
            }

            $private_chat_object->setChatMessage($data['msg']);

            $timestamp = date('Y-m-d h:i:s');

            $private_chat_object->setTimestamp($timestamp);

            $private_chat_object->setStatus('Yes');

            $private_chat_object->setAttachment($attachment);

            $chat_message_id = $private_chat_object->save_chat();

            $conversation_id = $private_chat_object->getConversationId();
            $conversation_customer_id = $private_chat_object->getConversationCustomerId();

            $user_object = new \ChatUser;

            $user_object->setUserId($data['userId']);

            $sender_user_data = $user_object->get_user_data_by_id();

            $user_object->setUserId($data['receiver_userid']);

            $receiver_user_data = $user_object->get_user_data_by_id();

            $sender_user_name = $sender_user_data['user_name'];
            $sender_user_type = $sender_user_data['user_type'] ?? null;

            $data['datetime'] = $timestamp;
            if($attachment)
            {
                $data['attachment'] = $attachment;
            }
            if($sender_user_type)
            {
                $data['from_user_type'] = $sender_user_type;
            }

            if($conversation_id)
            {
                $data['conversation_id'] = $conversation_id;
            }

            if($conversation_customer_id)
            {
                $data['conversation_customer_id'] = $conversation_customer_id;
            }

            $receiver_user_connection_id = $receiver_user_data['user_connection_id'];

            $this->logDebug('Dispatching private message', [
                'from_user_id' => $data['userId'],
                'to_user_id' => $data['receiver_userid'],
                'sender_connection' => $from->resourceId,
                'receiver_connection' => $receiver_user_connection_id,
                'chat_message_id' => $chat_message_id
            ]);

            foreach($this->clients as $client)
            {
                if($from == $client)
                {
                    $data['from'] = 'Me';
                }
                else
                {
                    $data['from'] = $sender_user_name;
                }

                if($client->resourceId == $receiver_user_connection_id || $from == $client)
                {   
                    $client->send(json_encode($data));
                }
                else
                {
                    $private_chat_object->setStatus('No');
                    $private_chat_object->setChatMessageId($chat_message_id);

                    $private_chat_object->update_chat_status();

                    $this->logDebug('Queued private message for delivery when receiver reconnects', [
                        'chat_message_id' => $chat_message_id
                    ]);
                }
            }
        }
        else
        {
            //group chat

            $chat_object = new \ChatRooms;

            $chat_object->setUserId($data['userId']);

            $chat_object->setMessage($data['msg']);

            $chat_object->setCreatedOn(date("Y-m-d h:i:s"));

            $chat_object->setAttachment($attachment);

            $chat_object->save_chat();

            $user_object = new \ChatUser;

            $user_object->setUserId($data['userId']);

            $user_data = $user_object->get_user_data_by_id();

            $user_name = $user_data['user_name'];

            $this->logDebug('Dispatching group message', [
                'from_user_id' => $data['userId'],
                'resource_id' => $from->resourceId,
                'total_clients' => count($this->clients)
            ]);

            $data['dt'] = date("d-m-Y h:i:s");
            if($attachment)
            {
                $data['attachment'] = $attachment;
            }


            foreach ($this->clients as $client) {
                /*if ($from !== $client) {
                    // The sender is not the receiver, send to each client connected
                    $client->send($msg);
                }*/

                if($from == $client)
                {
                    $data['from'] = 'Me';
                }
                else
                {
                    $data['from'] = $user_name;
                }

                $client->send(json_encode($data));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {

        $querystring = $conn->httpRequest->getUri()->getQuery();

        parse_str($querystring, $queryarray);

        if(isset($queryarray['token']))
        {

            $user_object = new \ChatUser;

            $user_object->setUserToken($queryarray['token']);

            $user_data = $user_object->get_user_id_from_token();

            $user_id = isset($user_data['user_id']) ? $user_data['user_id'] : null;

            $data['status_type'] = 'Offline';

            $data['user_id_status'] = $user_id;

            if($user_id)
            {
                $this->logDebug('User disconnected', [
                    'user_id' => $user_id,
                    'resource_id' => $conn->resourceId
                ]);

                foreach($this->clients as $client)
                {
                    $client->send(json_encode($data));
                }
            }
            else
            {
                $this->logDebug('Token provided at disconnect but user_id missing', [
                    'resource_id' => $conn->resourceId
                ]);
            }
        }
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $this->logDebug('Socket error', [
            'resource_id' => $conn->resourceId,
            'message' => $e->getMessage()
        ]);

        $conn->close();
    }

    protected function logDebug(string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = '[' . $timestamp . '] ' . $message;

        if(!empty($context))
        {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if($encoded !== false)
            {
                $line .= ' ' . $encoded;
            }
        }

        @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND);
    }
}

?>
