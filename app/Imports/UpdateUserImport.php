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
                        'cabang_id' => $row[1],
                        'name' => $row[2],
                        'email' => $row[3],
                        'username' => $row[4],
                        'password' => $row[5]
                    ]
                );
            }
            
        }
    }
}
