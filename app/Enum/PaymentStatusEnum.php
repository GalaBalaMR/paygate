<?php
  
namespace App\Enum;
 
enum PaymentStatusEnum:string {
    case Active = 'active';
    case Expired = 'expired';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Error = 'error';
}