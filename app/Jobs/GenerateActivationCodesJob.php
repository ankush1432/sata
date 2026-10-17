<?php

namespace App\Jobs;

use App\Enums\ActivationCodeStatus;
use App\Models\ActivationCode;
use App\Models\ActivationCodeBatch;
use App\Models\ActivationPlan;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateActivationCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ActivationPlan $plan,
        public int $quantity,
        public ?Admin $admin = null,
        public ?ActivationCodeBatch $batch = null,
        public ?string $notes = null
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $batch = $this->batch;
            if (! $batch) {
                $batchNumber = 'BATCH-' . strtoupper(Str::random(8));
                $batch = ActivationCodeBatch::create([
                    'batch_number' => $batchNumber,
                    'activation_plan_id' => $this->plan->id,
                    'plan_id' => $this->plan->id,
                    'created_by_admin_id' => $this->admin?->id,
                    'quantity' => $this->quantity,
                    'notes' => $this->notes,
                ]);
            }

            $currentSequence = ActivationCode::getNextSerialSequence();
            $chunkSize = 250;
            $totalGenerated = 0;

            while ($totalGenerated < $this->quantity) {
                $countInChunk = min($chunkSize, $this->quantity - $totalGenerated);
                $records = [];

                for ($i = 0; $i < $countInChunk; $i++) {
                    $serialNum = sprintf('SAT-%06d', $currentSequence++);
                    $plainCode = ActivationCode::generateSecureCode();
                    $normalized = ActivationCode::normalizeCode($plainCode);
                    $codeHash = hash('sha256', $normalized);
                    $codeLast4 = substr($normalized, -4);

                    $records[] = [
                        'serial_number' => $serialNum,
                        'code' => $plainCode,
                        'code_hash' => $codeHash,
                        'code_last4' => $codeLast4,
                        'activation_plan_id' => $this->plan->id,
                        'plan_id' => $this->plan->id,
                        'batch_id' => $batch->id,
                        'status' => ActivationCodeStatus::AVAILABLE->value,
                        'is_redeemed' => false,
                        'is_active' => true,
                        'generated_by_admin_id' => $this->admin?->id,
                        'created_by_admin_id' => $this->admin?->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                ActivationCode::insert($records);
                $totalGenerated += $countInChunk;
            }
        });
    }
}
