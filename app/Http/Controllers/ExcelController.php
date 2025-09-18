<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode; 
use Illuminate\Support\Facades\Storage;
use App\Models\Student;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;



/**
 * @OA\Post(
 *     path="/api/upload-excel",
 *     summary="Uploader un fichier Excel et retourner son contenu",
 *     tags={"Excel"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(
 *                     property="excel_file",
 *                     type="string",
 *                     format="binary",
 *                     description="Fichier Excel à uploader"
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Succès",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="array", @OA\Items(type="array", @OA\Items(type="string")))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Erreur de validation ou fichier vide"),
 *     @OA\Response(response=500, description="Erreur serveur")
 * )
 */

class ExcelController extends Controller
{


    
     public function uploadAndParse(Request $request)
        {
            try {
                $request->validate([
                    'excel_file' => 'required|file|mimes:xlsx,xls',
                ]);

                $file = $request->file('excel_file');
                $spreadsheet = IOFactory::load($file->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();

                $data = [];
                foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
                    $rowData = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $value = $cell->getValue();

                        // Détecter si la cellule est une date Excel
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                            try {
                                // Convertir le nombre Excel en DateTime
                                $date = ExcelDate::excelToDateTimeObject($value);
                                $value = $date->format('d/m/Y'); // format lisible pour le front
                            } catch (\Exception $e) {
                                $value = (string)$value;
                            }
                        }

                        $rowData[] = $value ?? '';
                    }

                    $data[] = $rowData;
                }

                if (empty($data) || empty($data[0])) {
                    return response()->json(['error' => 'Le fichier Excel est vide'], 400);
                }

                return response()->json(['data' => $data]);
            } catch (\Exception $e) {
                Log::error('Erreur lors du traitement du fichier Excel : ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
            }
            
        }


    public function generateQRCodes(Request $request)
        {
            try {
                $file = $request->file('excel_file');
                if (!$file) {
                    return response()->json(['error' => 'Aucun fichier fourni'], 400);
                }

                $spreadsheet = IOFactory::load($file->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();

                $qrcodes = [];
                $isFirstRow = true;

                foreach ($worksheet->getRowIterator() as $row) {
                    $rowData = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $rowData[] = $cell->getValue() ?? '';
                    }

                    // Ignorer la première ligne (header)
                    if ($isFirstRow) {
                        $isFirstRow = false;
                        continue;
                    }

                    if (!empty($rowData)) {
                        // Respecter l'ordre : Examen | Date | ID | Nom | Prénom | CIN
                        [$exam, $examDateRaw, $studentId, $lastName, $firstName, $cin] = $rowData;

                        // --- Normaliser la date ---
                        $examDate = null;

                        if (is_numeric($examDateRaw)) {
                            // Si c'est une date Excel
                            try {
                                $examDateObj = ExcelDate::excelToDateTimeObject($examDateRaw);
                                $examDate = $examDateObj->format('Y-m-d');
                            } catch (\Exception $e) {
                                $examDate = null;
                            }
                        } else {
                            // Si c'est une chaîne texte, gérer formats 10_12_2026 ou 12/01/2025
                            $examDateRaw = str_replace(['_', '/'], '-', $examDateRaw); // transforme 10_12_2026 => 10-12-2026
                            try {
                                $examDate = \Carbon\Carbon::createFromFormat('d-m-Y', $examDateRaw)->format('Y-m-d');
                            } catch (\Exception $e) {
                                $examDate = null;
                            }
                        }

                        // --- Générer hash QR code ---
                        $rowString = implode('|', $rowData);
                        $hashed = hash('sha256', $rowString);

                        // --- Générer QR code ---
                        $qrCodePng = QrCode::format('png')->size(200)->generate($hashed);

                        // --- Sauvegarder QR code ---
                        $fileName = "qrcodes/{$cin}_{$exam}.png";
                        Storage::disk('public')->put($fileName, $qrCodePng);

                        // --- Insertion / update en DB ---
                        $student = Student::updateOrCreate(
                            ['cin' => $cin, 'exam' => $exam],
                            [
                                'first_name' => $firstName,
                                'last_name'  => $lastName,
                                'student_id' => $studentId,
                                'exam_date'  => $examDate,
                                'qr_hash'    => $hashed,
                                'qr_path'    => "storage/" . $fileName,
                            ]
                        );

                        $qrcodes[] = [
                            'id'         => $student->id,
                            'student_id' => $studentId,
                            'nom'        => $lastName,
                            'prenom'     => $firstName,
                            'cin'        => $cin,
                            'exam'       => $exam,
                            'exam_date'  => $examDate,
                            'hash'       => $hashed,
                            'qrcode'     => base64_encode($qrCodePng),
                        ];
                    }
                }

                return response()->json(['qrcodes' => $qrcodes]);

            } catch (\Exception $e) {
                Log::error('Erreur lors de la génération des QR codes : ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
            }
        }





   public function generatePDF(Request $request)
{
    try {
        // Valider la requête
        $request->validate([
            'qrcodes' => 'required|array',
            'qrcodes.*.qrcode' => 'required|string',
            'qrcodes.*.student_id' => 'required|string',
            'qrcodes.*.nom' => 'required|string',
            'qrcodes.*.prenom' => 'required|string',
            'qrcodes.*.cin' => 'required|string',
            'qrcodes.*.exam' => 'required|string',
            'qrcodes.*.exam_date' => 'required|string',
        ]);

        $qrcodes = $request->input('qrcodes');

        // Filtrer les QR codes non nuls
        $qrcodes = array_filter($qrcodes, fn($value) => isset($value['qrcode']) && $value['qrcode'] !== '');

        if (empty($qrcodes)) {
            return response()->json(['error' => 'Aucun QR code valide fourni'], 400);
        }

        // Vérifier la vue
        if (!view()->exists('qrcodes')) {
            throw new \Exception('Vue qrcodes introuvable');
        }

        // Générer le PDF
        $pdf = Pdf::loadView('qrcodes', ['qrcodes' => array_values($qrcodes)]);

        // Télécharger le PDF
        return $pdf->download('qrcodes.pdf');

    } catch (\Exception $e) {
        Log::error('Erreur lors de la génération du PDF: ' . $e->getMessage(), [
            'request' => $request->all(),
            'exception' => $e,
        ]);

        return response()->json([
            'error' => 'Erreur serveur: ' . $e->getMessage()
        ], 500);
    }
}

}
