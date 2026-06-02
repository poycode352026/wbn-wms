<?php

namespace App\Console\Commands;

use App\Http\Controllers\GoodsReceiptController;
use App\Models\GoodsReceipt;
use Illuminate\Console\Command;

class AutoApproveGoodsReceipts extends Command
{
    protected $signature   = 'gr:auto-approve';
    protected $description = 'Auto-approve GR yang sudah pending_supervisor lebih dari 24 jam';

    public function handle(): void
    {
        $grs = GoodsReceipt::where('status', 'pending_supervisor')
            ->where('inspected_at', '<=', now()->subHours(24))
            ->with(['items.location'])
            ->get();

        $count = 0;
        foreach ($grs as $gr) {
            try {
                GoodsReceiptController::doApprove($gr, null, true);
                $count++;
                $this->info("Auto-approved: {$gr->gr_number}");
            } catch (\Throwable $e) {
                $this->error("Gagal auto-approve {$gr->gr_number}: {$e->getMessage()}");
            }
        }

        $this->info("Selesai: {$count} GR di-auto-approve.");
    }
}
