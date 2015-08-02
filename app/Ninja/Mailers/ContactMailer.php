<?php namespace App\Ninja\Mailers;

use Utils;
use Event;
use URL;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Activity;
use App\Models\Gateway;
use App\Events\InvoiceSent;

class ContactMailer extends Mailer
{
    public function sendInvoice(Invoice $invoice)
    {
        $invoice->load('invitations', 'client', 'account');
        $entityType = $invoice->getEntityType();

        $view = 'invoice';
        $subject = trans("texts.{$entityType}_subject", ['invoice' => $invoice->invoice_number, 'account' => $invoice->account->getDisplayName()]);
        $accountName = $invoice->account->getDisplayName();
        $emailTemplate = $invoice->account->getEmailTemplate($entityType);
        $invoiceAmount = Utils::formatMoney($invoice->getRequestedAmount(), $invoice->client->getCurrencyId());

        $this->initClosure($invoice);

        foreach ($invoice->invitations as $invitation) {
            if (!$invitation->user || !$invitation->user->email || $invitation->user->trashed()) {
                return false;
            }
            if (!$invitation->contact || !$invitation->contact->email || $invitation->contact->trashed()) {
                return false;
            }

            $invitation->sent_date = \Carbon::now()->toDateTimeString();
            $invitation->save();

            $variables = [
                '$footer' => $invoice->account->getEmailFooter(),
                '$link' => $invitation->getLink(),
                '$client' => $invoice->client->getDisplayName(),
                '$account' => $accountName,
                '$contact' => $invitation->contact->getDisplayName(),
                '$amount' => $invoiceAmount,
                '$advancedRawInvoice->' => '$'
            ];

            // Add variables for available payment types
            foreach (Gateway::getPaymentTypeLinks() as $type) {
                $variables["\${$type}_link"] = URL::to("/payment/{$invitation->invitation_key}/{$type}");
            }

            $data['body'] = str_replace(array_keys($variables), array_values($variables), $emailTemplate);
            $data['body'] = preg_replace_callback('/\{\{\$?(.*)\}\}/', $this->advancedTemplateHandler, $data['body']);
            $data['link'] = $invitation->getLink();
            $data['entityType'] = $entityType;
            $data['invoice_id'] = $invoice->id;

            $fromEmail = $invitation->user->email;
            $response = $this->sendTo($invitation->contact->email, $fromEmail, $accountName, $subject, $view, $data);

            if ($response !== true) {
                return $response;
            }

            Activity::emailInvoice($invitation);
        }

        if (!$invoice->isSent()) {
            $invoice->invoice_status_id = INVOICE_STATUS_SENT;
            $invoice->save();
        }

        Event::fire(new InvoiceSent($invoice));

        return $response;
    }

    public function sendPaymentConfirmation(Payment $payment)
    {
        $invoice = $payment->invoice;
        $view = 'payment_confirmation';
        $subject = trans('texts.payment_subject', ['invoice' => $invoice->invoice_number]);
        $accountName = $payment->account->getDisplayName();
        $emailTemplate = $invoice->account->getEmailTemplate(ENTITY_PAYMENT);

        $variables = [
            '$footer' => $payment->account->getEmailFooter(),
            '$client' => $payment->client->getDisplayName(),
            '$account' => $accountName,
            '$amount' => Utils::formatMoney($payment->amount, $payment->client->getCurrencyId())
        ];

        $data = ['body' => str_replace(array_keys($variables), array_values($variables), $emailTemplate)];

        if ($payment->invitation) {
            $user = $payment->invitation->user;
            $contact = $payment->contact;
        } else {
            $user = $payment->user;
            $contact = $payment->client->contacts[0];
        }

        if ($user->email && $contact->email) {
            $this->sendTo($contact->email, $user->email, $accountName, $subject, $view, $data);
        }
    }

    public function sendLicensePaymentConfirmation($name, $email, $amount, $license, $productId)
    {
        $view = 'license_confirmation';
        $subject = trans('texts.payment_subject');
        
        if ($productId == PRODUCT_ONE_CLICK_INSTALL) {
            $license = "Softaculous install license: $license";
        } elseif ($productId == PRODUCT_INVOICE_DESIGNS) {
            $license = "Invoice designs license: $license";
        } elseif ($productId == PRODUCT_WHITE_LABEL) {
            $license = "White label license: $license";
        }
        
        $data = [
            'account' => trans('texts.email_from'),
            'client' => $name,
            'amount' => Utils::formatMoney($amount, 1),
            'license' => $license
        ];
        
        $this->sendTo($email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    private function initClosure($object)
    {
        $this->advancedTemplateHandler = function($match) use ($object) {
            for ($i = 1; $i < count($match); $i++) {
                $blobConversion = $match[$i];

                if (isset($$blobConversion)) {
                    return $$blobConversion;
                } else if (preg_match('/trans\(([\w\.]+)\)/', $blobConversion, $regexTranslation)) {
                    return trans($regexTranslation[1]);
                } else if (strpos($blobConversion, '->') !== false) {
                    return Utils::stringToObjectResolution($object, $blobConversion);
                }

            }
        };
    }
}
