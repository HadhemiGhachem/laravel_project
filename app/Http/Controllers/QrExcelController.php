<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Crypt;


class QrExcelController extends Controller
{
    // function import data from file excel 
    public function import()
    {
        $cheminFichier = 'C:/Users/USER/Downloads/data.xlsx';
        $rows = Excel::toCollection(null, $cheminFichier)[0];

        $qrs = [];

        foreach ($rows->slice(1) as $row) {
            $data = [
                'id' => $row[0],
                'nom' => $row[1],
                'prenom' => $row[2],
                'examen' => $row[3]
            ];

            $chiffre = Crypt::encrypt(json_encode($data));

            $qrs[] = QrCode::size(200)->generate($chiffre);


        }

        return view('qr-multiple', compact('qrs'));
    }

    // fonction return form des données dechiffer
    public function formDechiffrement()
    {
        return view('dechiffrer-form');
    }


    // fonction dechiffrement
    public function dechiffrer(Request $request)
    {
        try {
            $chaine = $request->input('code');

            $json = Crypt::decrypt($chaine);
            $data = json_decode($json);

            return view('dechiffrer-resultat', compact('data'));
        } catch (\Exception $e) {
            return back()->withErrors(['code' => 'Code invalide ou non déchiffrable.']);
        }



        
    }

}