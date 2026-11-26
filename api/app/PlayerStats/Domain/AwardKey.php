<?php

namespace App\PlayerStats\Domain;

// The awards, in the order the page shows them.
enum AwardKey: string
{
    case Legshots = 'legshots';
    case FirstBlood = 'first_blood';
    case Headshot = 'headshot';
    case Eco = 'eco';
    case Clutches = 'clutches';
    case OneTrick = 'one_trick';
}
