<?php

namespace App\Enums;

enum TaskActivityType: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case Completed = 'completed';
}
