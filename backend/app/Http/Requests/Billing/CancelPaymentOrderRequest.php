<?php

namespace App\Http\Requests\Billing;

use App\Models\PaymentOrder;
use Illuminate\Foundation\Http\FormRequest;

class CancelPaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $orderId = $this->route('id');
        if (! $orderId) {
            return true;
        }

        $order = PaymentOrder::find($orderId);
        if (! $order) {
            return true;
        }

        return (bool) $user->can('cancel', $order);
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
