<?php namespace App\Models;

use Eloquent;
use Utils;
use Session;
use DateTime;
use Event;
use App;
use App\Events\UserSettingsChanged;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Eloquent
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    /*
    protected $casts = [
        'hide_quantity' => 'boolean',
    ];
    */
    
    public function users()
    {
        return $this->hasMany('App\Models\User');
    }

    public function clients()
    {
        return $this->hasMany('App\Models\Client');
    }

    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    public function account_gateways()
    {
        return $this->hasMany('App\Models\AccountGateway');
    }

    public function tax_rates()
    {
        return $this->hasMany('App\Models\TaxRate');
    }

    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    public function timezone()
    {
        return $this->belongsTo('App\Models\Timezone');
    }

    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    public function date_format()
    {
        return $this->belongsTo('App\Models\DateFormat');
    }

    public function datetime_format()
    {
        return $this->belongsTo('App\Models\DatetimeFormat');
    }

    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    public function isGatewayConfigured($gatewayId = 0)
    {
        $this->load('account_gateways');

        if ($gatewayId) {
            return $this->getGatewayConfig($gatewayId) != false;
        } else {
            return count($this->account_gateways) > 0;
        }
    }

    public function isEnglish()
    {
        return !$this->language_id || $this->language_id == DEFAULT_LANGUAGE;
    }

    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }

        $this->load('users');
        $user = $this->users()->first();

        return $user->getDisplayName();
    }

    public function getTimezone()
    {
        if ($this->timezone) {
            return $this->timezone->name;
        } else {
            return 'US/Eastern';
        }
    }

    public function getGatewayByType($type = PAYMENT_TYPE_ANY)
    {
        foreach ($this->account_gateways as $gateway) {
            if (!$type || $type == PAYMENT_TYPE_ANY) {
                return $gateway;
            } elseif ($gateway->isPaymentType($type)) {
                return $gateway;
            }
        }
        
        return false;
    }

    public function getGatewayConfig($gatewayId)
    {
        foreach ($this->account_gateways as $gateway) {
            if ($gateway->gateway_id == $gatewayId) {
                return $gateway;
            }
        }

        return false;
    }

    /*
    public function hasLogo()
    {
        file_exists($this->getLogoPath());
    }
    */

    public function getLogoPath()
    {
        $fileName = 'logo/' . $this->account_key;

        return file_exists($fileName.'.png') ? $fileName.'.png' : $fileName.'.jpg';
    }

    public function getLogoWidth()
    {
        $path = $this->getLogoPath();
        if (!file_exists($path)) {
            return 0;
        }
        list($width, $height) = getimagesize($path);

        return $width;
    }

    public function getLogoHeight()
    {
        $path = $this->getLogoPath();
        if (!file_exists($path)) {
            return 0;
        }
        list($width, $height) = getimagesize($path);

        return $height;
    }

    public function getNextInvoiceNumber($isQuote = false, $prefix = '')
    {
        $counter = $isQuote && !$this->share_counter ? $this->quote_number_counter : $this->invoice_number_counter;
        $prefix .= $isQuote ? $this->quote_number_prefix : $this->invoice_number_prefix;
        $counterOffset = 0;

        // confirm the invoice number isn't already taken 
        do {
            $number = $prefix.str_pad($counter, 4, "0", STR_PAD_LEFT);
            $check = Invoice::scope(false, $this->id)->whereInvoiceNumber($number)->withTrashed()->first();
            $counter++;
            $counterOffset++;
        } while ($check);

        // update the invoice counter to be caught up
        if ($counterOffset > 1) {
            if ($isQuote && !$this->share_counter) {
                $this->quote_number_counter += $counterOffset - 1;
            } else {
                $this->invoice_number_counter += $counterOffset - 1;
            }

            $this->save();
        }

        return $number;
    }

    public function incrementCounter($isQuote = false)
    {
        if ($isQuote && !$this->share_counter) {
            $this->quote_number_counter += 1;
        } else {
            $this->invoice_number_counter += 1;
        }

        $this->save();
    }

    public function getLocale()
    {
        $language = Language::where('id', '=', $this->account->language_id)->first();

        return $language->locale;
    }

    public function loadLocalizationSettings()
    {
        $this->load('timezone', 'date_format', 'datetime_format', 'language');

        Session::put(SESSION_TIMEZONE, $this->timezone ? $this->timezone->name : DEFAULT_TIMEZONE);
        Session::put(SESSION_DATE_FORMAT, $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT);
        Session::put(SESSION_DATE_PICKER_FORMAT, $this->date_format ? $this->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT);
        Session::put(SESSION_DATETIME_FORMAT, $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT);
        Session::put(SESSION_CURRENCY, $this->currency_id ? $this->currency_id : DEFAULT_CURRENCY);
        Session::put(SESSION_LOCALE, $this->language_id ? $this->language->locale : DEFAULT_LOCALE);

        App::setLocale(session(SESSION_LOCALE));
    }

    public function getInvoiceLabels()
    {
        $data = [];
        $custom = (array) json_decode($this->invoice_labels);

        $fields = [
            'invoice',
            'invoice_date',
            'due_date',
            'invoice_number',
            'po_number',
            'discount',
            'taxes',
            'tax',
            'item',
            'description',
            'unit_cost',
            'quantity',
            'line_total',
            'subtotal',
            'paid_to_date',
            'balance_due',
            'amount_due',
            'terms',
            'your_invoice',
            'quote',
            'your_quote',
            'quote_date',
            'quote_number',
            'total',
            'invoice_issued_to',
            'date',
            'rate',
            'hours',
            'balance',
            'from',
            'to',
            'invoice_to',
            'details',
            'invoice_no',
        ];

        foreach ($fields as $field) {
            if (isset($custom[$field]) && $custom[$field]) {
                $data[$field] = $custom[$field];
            } else {
                $data[$field] = $this->isEnglish() ? uctrans("texts.$field") : trans("texts.$field");
            }
        }

        foreach (['item', 'quantity', 'unit_cost'] as $field) {
            $data["{$field}_orig"] = $data[$field];
        }

        return $data;
    }

    public function isPro()
    {
        if (!Utils::isNinjaProd()) {
            return true;
        }

        if ($this->account_key == NINJA_ACCOUNT_KEY) {
            return true;
        }

        $datePaid = $this->pro_plan_paid;

        if (!$datePaid || $datePaid == '0000-00-00') {
            return false;
        } elseif ($datePaid == NINJA_DATE) {
            return true;
        }

        $today = new DateTime('now');
        $datePaid = DateTime::createFromFormat('Y-m-d', $datePaid);
        $interval = $today->diff($datePaid);

        return $interval->y == 0;
    }

    public function isWhiteLabel()
    {
        if (Utils::isNinjaProd()) {
            return self::isPro() && $this->pro_plan_paid != NINJA_DATE;
        } else {
            return $this->pro_plan_paid == NINJA_DATE;
        }
    }

    public function getSubscription($eventId)
    {
        return Subscription::where('account_id', '=', $this->id)->where('event_id', '=', $eventId)->first();
    }

    public function hideFieldsForViz()
    {
        foreach ($this->clients as $client) {
            $client->setVisible([
                'public_id',
                'name',
                'balance',
                'paid_to_date',
                'invoices',
                'contacts',
            ]);

            foreach ($client->invoices as $invoice) {
                $invoice->setVisible([
                    'public_id',
                    'invoice_number',
                    'amount',
                    'balance',
                    'invoice_status_id',
                    'invoice_items',
                    'created_at',
                    'is_recurring',
                    'is_quote',
                ]);

                foreach ($invoice->invoice_items as $invoiceItem) {
                    $invoiceItem->setVisible([
                        'product_key',
                        'cost',
                        'qty',
                    ]);
                }
            }

            foreach ($client->contacts as $contact) {
                $contact->setVisible([
                    'public_id',
                    'first_name',
                    'last_name',
                    'email', ]);
            }
        }

        return $this;
    }

    public function getEmailTemplate($entityType, $message = false)
    {
        $field = "email_template_$entityType";
        $template = $this->$field;

        if ($template) {
            return $template;
        }

        $template = "\$client,<p/>\r\n\r\n" .
                    trans("texts.{$entityType}_message", ['amount' => '$amount']) . "<p/>\r\n\r\n";

        if ($entityType != ENTITY_PAYMENT) {
            $template .= "<a href=\"\$link\">\$link</a><p/>\r\n\r\n";
        }

        if ($message) {
            $template .= "$message<p/>\r\n\r\n";
        }

        return $template . "\$footer";
    }

    public function getEmailFooter()
    {
        if ($this->email_footer) {
            // Add line breaks if HTML isn't already being used
            return strip_tags($this->email_footer) == $this->email_footer ? nl2br($this->email_footer) : $this->email_footer;            
        } else {
            return "<p>" . trans('texts.email_signature') . "<br>\$account</p>";
        }
    }

    public function showTokenCheckbox()
    {
        if (!$this->isGatewayConfigured(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->token_billing_type_id == TOKEN_BILLING_OPT_IN
                || $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }

    public function selectTokenCheckbox()
    {
        return $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }
}

Account::updated(function ($account) {
    Event::fire(new UserSettingsChanged());
});
