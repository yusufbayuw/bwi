<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class UpdateUserImport implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        foreach ($collection as $key => $row) {
            if ($key === 0) {
                //
            } else {
                User::updateOrCreate(
                    ['id' => $row[0],],
                    [
                        'name' => $row[1],
                        'email' => $row[2],
                        'username' => $row[3],
                        'password' => $row[4]
                    ]
                );
            }
            
        }
    }
}
