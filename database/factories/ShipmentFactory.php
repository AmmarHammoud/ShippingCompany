<?php
namespace Database\Factories;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition()
    {
        return [
            'sender_lat' => $this->faker->latitude,
            'sender_lng' => $this->faker->longitude,
            'recipient_location' => $this->faker->streetAddress . ', ' . 
                                    $this->faker->city . ', ' . 
                                    $this->faker->postcode,
            'recipient_lat' => $this->faker->latitude,
            'recipient_lng' => $this->faker->longitude,
            'shipment_type' => $this->faker->randomElement(['document', 'parcel', 'fragile']),
            'number_of_pieces' => $this->faker->numberBetween(1, 10),
            'weight' => $this->faker->numberBetween(1, 100),
            'size' => $this->faker->numberBetween(1, 10),
            'delivery_price' => $this->faker->numberBetween(10, 200),
            'product_value' => $this->faker->randomFloat(2, 10, 500),
            'total_amount' => function (array $attributes) {
                return $attributes['delivery_price'] + $attributes['product_value'];
            },
            'status' => $this->faker->randomElement([
                'pending',               
                'offered_pickup_driver', 
                'picked_up',             
                'in_transit_between_centers', 
                'arrived_at_destination_center', 
                'offered_delivery_driver', 
                'out_for_delivery',      
                'delivered',             
                'cancelled',
            ]),
        ];
    }
}