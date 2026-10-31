<?php

namespace App\Services\Order;

use App\Models\GameDeliveryQueue;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderDeliveryService
{
    /**
     * Queue in-game commands for paid order.
     */
    public function queueDeliveries(Order $order): int
    {
        // Must be paid
        if ($order->payment_status !== 'paid') {
            return 0;
        }

        // Idempotency: if deliveries already exist for this order, do not duplicate
        if (GameDeliveryQueue::where('order_id', $order->id)->exists()) {
            Log::info("[OrderDelivery] Order {$order->order_code} already has delivery queue entries. Skipping.");
            return 0;
        }

        $player = !empty($order->minecraft_username)
            ? $order->minecraft_username
            : ($order->user?->minecraft_username ?: $order->user?->username);

        if (empty($player)) {
            Log::warning("[OrderDelivery] Order {$order->order_code} has no Minecraft username or user IGN. Postponing delivery.");
            return 0;
        }

        $order->loadMissing(['items.product', 'user']);
        $queuedCount = 0;

        foreach ($order->items as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            $gameReward = $product->game_reward ?: ($product->metadata['gameReward'] ?? '');
            if (empty($gameReward)) {
                continue;
            }

            $commands = $this->buildCommandsForReward($gameReward, $player);

            for ($qty = 0; $qty < $item->quantity; $qty++) {
                foreach ($commands as $cmd) {
                    GameDeliveryQueue::create([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'game_username' => $player,
                        'game_uuid' => $order->user?->minecraft_uuid,
                        'command' => $cmd,
                        'type' => 'order_delivery',
                        'status' => 'pending',
                    ]);
                    $queuedCount++;
                }
            }
        }

        Log::info("[OrderDelivery] Queued {$queuedCount} commands for Order {$order->order_code} (Player: {$player}).");

        return $queuedCount;
    }

    /**
     * Map game reward identifier to server console commands.
     */
    protected function buildCommandsForReward(string $reward, string $player): array
    {
        $reward = strtolower(trim($reward));

        // Rank Rewards: rank_knight, rank_legend, rank_master, rank_overlord, rank_god
        if (str_starts_with($reward, 'rank_')) {
            $rankName = substr($reward, 5); // knight, legend, master, overlord, god
            $rankTitle = ucfirst($rankName);

            return [
                "lp user {$player} parent set {$rankName}",
                "broadcast &e&lĐẢO TÒ MÒ &8» &fNgười chơi &a{$player} &fvừa mở khóa thành công &b&lRank {$rankTitle}! &fCảm ơn bạn đã ủng hộ máy chủ!",
            ];
        }

        // Coin Rewards
        if ($reward === 'coin_100k') {
            return [
                "coins give {$player} 100000",
                "tellraw {$player} {\"text\":\"[Đảo Tò Mò] Bạn đã nhận được 100.000 Coin!\",\"color\":\"green\"}",
            ];
        }

        // Crate Rewards
        if ($reward === 'epic_crate_x1') {
            return [
                "excellentcrates:crate key give {$player} epic 1",
                "tellraw {$player} {\"text\":\"[Đảo Tò Mò] Bạn đã nhận được 1x Chìa Khóa Epic!\",\"color\":\"light_purple\"}",
            ];
        }

        return [];
    }
}
