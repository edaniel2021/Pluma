<?php

namespace Tests\Feature;

use App\Domain\Marketplace\Models\MarketplaceOrder;
use App\Domain\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Low-priority schema stub (see the migrations) - just enough coverage to
 * confirm the relations resolve correctly, not full CRUD/UI coverage.
 */
class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_links_its_buyer_seller_and_messages(): void
    {
        $buyer = Organization::factory()->create();
        $seller = Organization::factory()->create();

        $order = MarketplaceOrder::create([
            'buyer_organization_id' => $buyer->id,
            'seller_organization_id' => $seller->id,
            'title' => 'Manage our Instagram for a month',
            'amount' => 250,
        ]);

        $message = $order->messages()->create([
            'sender_organization_id' => $buyer->id,
            'content' => 'When can you start?',
        ]);

        $this->assertTrue($order->buyer->is($buyer));
        $this->assertTrue($order->seller->is($seller));
        $this->assertTrue($message->order->is($order));
        $this->assertTrue($message->sender->is($buyer));
        $this->assertSame('pending', $order->fresh()->status);
    }
}
