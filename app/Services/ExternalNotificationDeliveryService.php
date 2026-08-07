<?php

namespace App\Services;

use App\Models\Client;
use App\Models\NotificationDeliveryLog;
use App\Models\Setting;

class ExternalNotificationDeliveryService
{
    public function prepare(
        string $eventKey,
        ?Client $client,
        string $title,
        string $message
    ): array {
        if (!$client) {
            return [];
        }

        $result = [];

        $result['email'] =
            $this->prepareEmail(
                $eventKey,
                $client,
                $title,
                $message
            );

        $result['sms'] =
            $this->prepareSms(
                $eventKey,
                $client,
                $title,
                $message
            );

        $result['whatsapp'] =
            $this->prepareWhatsApp(
                $eventKey,
                $client,
                $title,
                $message
            );

        return $result;
    }

    public function channelStatus(): array
    {
        return [
            'email' => [
                'enabled' =>
                    Setting::bool(
                        'email_notifications_enabled',
                        false
                    ),

                'configured' =>
                    filled(
                        Setting::getValue(
                            'smtp_host',
                            ''
                        )
                    )
                    && filled(
                        Setting::getValue(
                            'smtp_from_email',
                            ''
                        )
                    ),
            ],

            'sms' => [
                'enabled' =>
                    Setting::bool(
                        'sms_notifications_enabled',
                        false
                    ),

                'configured' =>
                    filled(
                        Setting::getValue(
                            'sms_api_url',
                            ''
                        )
                    )
                    && filled(
                        Setting::getValue(
                            'sms_api_token',
                            ''
                        )
                    ),
            ],

            'whatsapp' => [
                'enabled' =>
                    Setting::bool(
                        'whatsapp_notifications_enabled',
                        false
                    ),

                'configured' =>
                    filled(
                        Setting::getValue(
                            'whatsapp_api_url',
                            ''
                        )
                    )
                    && filled(
                        Setting::getValue(
                            'whatsapp_api_token',
                            ''
                        )
                    ),
            ],
        ];
    }

    private function prepareEmail(
        string $eventKey,
        Client $client,
        string $title,
        string $message
    ): string {
        if (
            !Setting::bool(
                'email_notifications_enabled',
                false
            )
        ) {
            return 'disabled';
        }

        $recipient = trim(
            (string) $client->email
        );

        if ($recipient === '') {
            return $this->store(
                $eventKey,
                'email',
                $client,
                null,
                'no_recipient',
                $title,
                $message
            );
        }

        $configured =
            filled(
                Setting::getValue(
                    'smtp_host',
                    ''
                )
            )
            && filled(
                Setting::getValue(
                    'smtp_from_email',
                    ''
                )
            );

        return $this->store(
            $eventKey,
            'email',
            $client,
            $recipient,
            $configured
                ? 'waiting_provider'
                : 'waiting_configuration',
            $title,
            $message
        );
    }

    private function prepareSms(
        string $eventKey,
        Client $client,
        string $title,
        string $message
    ): string {
        if (
            !Setting::bool(
                'sms_notifications_enabled',
                false
            )
        ) {
            return 'disabled';
        }

        $recipient = trim(
            (string) $client->phone
        );

        if ($recipient === '') {
            return $this->store(
                $eventKey,
                'sms',
                $client,
                null,
                'no_recipient',
                $title,
                $message
            );
        }

        $configured =
            filled(
                Setting::getValue(
                    'sms_api_url',
                    ''
                )
            )
            && filled(
                Setting::getValue(
                    'sms_api_token',
                    ''
                )
            );

        return $this->store(
            $eventKey,
            'sms',
            $client,
            $recipient,
            $configured
                ? 'waiting_provider'
                : 'waiting_configuration',
            $title,
            $message
        );
    }

    private function prepareWhatsApp(
        string $eventKey,
        Client $client,
        string $title,
        string $message
    ): string {
        if (
            !Setting::bool(
                'whatsapp_notifications_enabled',
                false
            )
        ) {
            return 'disabled';
        }

        $recipient = trim(
            (string) $client->phone
        );

        if ($recipient === '') {
            return $this->store(
                $eventKey,
                'whatsapp',
                $client,
                null,
                'no_recipient',
                $title,
                $message
            );
        }

        $configured =
            filled(
                Setting::getValue(
                    'whatsapp_api_url',
                    ''
                )
            )
            && filled(
                Setting::getValue(
                    'whatsapp_api_token',
                    ''
                )
            );

        return $this->store(
            $eventKey,
            'whatsapp',
            $client,
            $recipient,
            $configured
                ? 'waiting_provider'
                : 'waiting_configuration',
            $title,
            $message
        );
    }

    private function store(
        string $eventKey,
        string $channel,
        Client $client,
        ?string $recipient,
        string $status,
        string $title,
        string $message
    ): string {
        /*
         * event_key + channel has a unique
         * DB constraint. Repeated scheduler
         * runs cannot create duplicates.
         */
        NotificationDeliveryLog::query()
            ->firstOrCreate(
                [
                    'event_key' =>
                        $eventKey,

                    'channel' =>
                        $channel,
                ],
                [
                    'client_id' =>
                        $client->id,

                    'recipient' =>
                        $recipient,

                    'status' =>
                        $status,

                    'attempts' => 0,

                    'payload' => [
                        'title' =>
                            $title,

                        'message' =>
                            $message,
                    ],
                ]
            );

        return $status;
    }
}
