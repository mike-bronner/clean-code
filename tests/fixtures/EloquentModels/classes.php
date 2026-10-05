<?php

declare(strict_types=1);

namespace App\Models {
    class InModels extends BaseModel
    {
    }
}

namespace App\Domain {
    use App\Other\Model as OtherModel;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Model as Eloquent;

    class Imported extends Model
    {
    }

    class Aliased extends Eloquent
    {
    }

    class Qualified extends \Illuminate\Database\Eloquent\Model
    {
    }

    class Lookalike extends OtherModel
    {
    }

    class Plain
    {
    }
}
