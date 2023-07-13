<?php
namespace verbb\formie\events;

use yii\base\Event;

class RegisterIntegrationsEvent extends Event
{
    // Properties
    // =========================================================================

    public $addressProviders = [];
    public $captchas = [];
    public $elements = [];
    public $emailMarketing = [];
    public $crm = [];
    public $webhooks = [];
    public $miscellaneous = [];

}
