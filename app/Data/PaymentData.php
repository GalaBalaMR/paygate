<?php

namespace App\Data;

use App\Data\UserData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Normalizers\JsonNormalizer;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Normalizers\ArrayNormalizer;
use Spatie\LaravelData\Normalizers\ModelNormalizer;
use Spatie\LaravelData\Normalizers\ObjectNormalizer;
use Spatie\LaravelData\Normalizers\ArrayableNormalizer;

class PaymentData extends Data
{
    public function __construct(
      public ?string $amount,
      public ?string $currency,
      public ?string $provider,
      #[DataCollectionOf(UserData::class)]
      public DataCollection $user,

    ) {}

    // public static function normalizers(): array
    // {
    //     return [
    //       ArrayNormalizer::class,
    //     ];
    // }
}
