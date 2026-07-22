<?php

declare(strict_types=1);

namespace Eva\Application\Query;

enum InputType: string
{
    case Direct = 'direct';
    case Structural = 'structural';
    case Conceptual = 'conceptual';
    case Relational = 'relational';
    case Broad = 'broad';
}
