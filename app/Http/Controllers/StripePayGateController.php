<?php

namespace App\Http\Controllers;

use Stripe;
use Exception;
use App\Models\User;
use App\Data\UserData;
use App\Models\Payment;
use App\Data\PaymentData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Enum\PaymentStatusEnum;
use App\Mail\Customer\PaymentInfo;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\CardException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class StripePayGateController extends Controller
{
    public function makePayment(Request $request)
    {
        // control url signature, which is make in /signed-url and added in Postman
        if (! $request->hasValidSignature()) {
            abort(401);
        }

        $user = User::find($request->user_id);

        $payment = Payment::create([
            'amount' => $request->amount,
            'currency' => $request->currency,
            'provider' => $request->provider,
            'user_id' => $request->user_id,
        ]);
        try {
            $stripe = new \Stripe\StripeClient(
                config('services.stripe.secret')
            );
            $res = $stripe->tokens->create([
                'card' => [
                    // $cardData
                    'number' => $request->number,
                    'exp_month' => $request->exp_month,
                    'exp_year' => $request->exp_year,
                    'cvc' => $request->cvc,
                ],
            ]);

            Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            $response = $stripe->charges->create([
                'amount' => $request->amount,
                'currency' => 'eur',
                'source' => 'tok_mastercard',
                'description' => 'Payment from good customer',
                'metadata' => ['user_id' => $user->id, 'payment_id' => $payment->id],
            ]);

            if ($response->status === 'succeeded') {
                Log::info('Platba od ' . $user->name . ' bola odoslana na stripe');
                $payment->status = PaymentStatusEnum::Active;
                $payment->save();
            } else {
                Log::error('Platba od ' . $user->name . ' hlasi problem: ' . $response->status);
                $payment->status = PaymentStatusEnum::Error;
                $payment->save();
            };

            $payment->user = $user;

            return response()->json(['payment' => [
                'currency' => $payment->currency,
                'amount' => $payment->amount,
            ], 'redirect_url' => url('/api/payExpire/'. $payment->id)], 201);

        } catch (CardException $e) {
            // Since it's a decline, \Stripe\Exception\CardException will be caught
            Log::error(PHP_EOL . 'Status is:' . $e->getHttpStatus() . '' . PHP_EOL . 'Type is:' . $e->getError()->type . '' . PHP_EOL . 'Code is:' . $e->getError()->code);
            // param is '' in this case
            Log::error(PHP_EOL . 'Param is:' . $e->getError()->param . '' . PHP_EOL . 'Message is:' . $e->getError()->message);
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            Log::error('Message: Too many requests made to the API too quickly' . PHP_EOL . 'Status: ' . $e->getHttpStatus());
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            Log::error('Message: Invalid parameters were supplied to Stripes API' . PHP_EOL . 'Status: ' . $e->getHttpStatus());
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            Log::error('Message: Authentication with Stripes API failed' . PHP_EOL . 'Status: ' . $e->getHttpStatus());
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            Log::error('Message: Network communication with Stripe failed' . PHP_EOL . 'Status: ' . $e->getHttpStatus());
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            // yourself an email
            Log::error('Message: This is some log, which can be email' . PHP_EOL . 'Status: ' . $e->getHttpStatus());
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            Log::error('Message: Something else happened, completely unrelated to Stripe');
        }
    }

    public function updateEndpoint(Request $request)
    {
        // require 'vendor/autoload.php';

        // The library needs to be configured with your account's secret key.
        // Ensure the key is kept out of any version control system you might be using.
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        // This is your Stripe CLI webhook secret for testing your endpoint locally.
        $endpoint_secret = 'whsec_ff682545dfd683dcbcf7576919c376a4ecc3473a2ecc07dde8a062b6a398dd99';

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );

            // Change payment status based on event
            $payment = Payment::with('user')->find($event->data->object->metadata->payment_id);

            if ($event->type === 'charge.succeeded') {
                $payment->status = PaymentStatusEnum::Paid;

                Log::info('Platba: ' . $payment->id . ' bola uspesne vyplatena');

                Mail::to($payment->user->email)->send(new PaymentInfo($payment));
            } elseif ($event->type === 'charge.refunded') {
                $payment->status = PaymentStatusEnum::Refunded;
                
                Log::error('Platba: ' . $payment->id . ' bola uspesne vratena');
                Mail::to($payment->user->email)->send(new PaymentInfo($payment));
            }
            $payment->save();

            Log::info('Platba: ' . $payment->id . ' bola uspesne vyplatena');
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Invalid payload');

            http_response_code(400);
            exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Invalid signature');

            http_response_code(400);
            exit();
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        http_response_code(200);
    }

    // if is younger than 24 hour, give him expired 
    // Verification
    public function payExpiration($id)
    {
        $payment = Payment::findOrFail($id);
        if($payment->created_at < Carbon::now()->subDay())
        {
            $payment->status = PaymentStatusEnum::Expired;
        }else{
            $payment->status = PaymentStatusEnum::Paid;
        }

        $payment->save();

        return response()->json([ 'payment_status' => $payment->status ]);
    }
}