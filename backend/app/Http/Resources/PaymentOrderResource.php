<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PaymentOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $proofUrl = null;
        if ($this->proof_image_path) {
            if (filter_var($this->proof_image_path, FILTER_VALIDATE_URL)) {
                $proofUrl = $this->proof_image_path;
            } else {
                $proofUrl = Storage::disk('public')->url($this->proof_image_path);
            }
        }

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'reference_code' => $this->reference_code,
            'amount_vnd' => (int) $this->amount_vnd,
            'amount_formatted' => number_format((int) $this->amount_vnd).'đ',
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'status' => $this->status?->value ?? (string) $this->status,
            'status_label' => $this->status?->label() ?? (string) $this->status,
            'bank_info_snapshot' => $this->bank_info_snapshot,
            'proof_image_path' => $this->proof_image_path,
            'proof_image_url' => $proofUrl,
            'customer_note' => $this->customer_note,
            'proof_submitted_at' => $this->proof_submitted_at?->toIso8601String(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'admin_note' => $this->admin_note,
            'rejection_reason' => $this->rejection_reason,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', function () {
                return new PlanResource($this->plan);
            }),
            'workspace' => $this->whenLoaded('workspace', function () {
                return [
                    'id' => $this->workspace->id,
                    'name' => $this->workspace->name,
                    'slug' => $this->workspace->slug,
                    'status' => $this->workspace->status?->value ?? (string) $this->workspace->status,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                    'email' => $this->reviewer->email,
                ];
            }),
            'transactions' => $this->whenLoaded('transactions', function () {
                return PaymentTransactionResource::collection($this->transactions);
            }),
        ];
    }
}
