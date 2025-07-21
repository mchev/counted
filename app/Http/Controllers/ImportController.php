<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {}

    public function index()
    {
        $sites = Site::where('user_id', auth()->id())->get();
        $importHistory = $this->importService->getImportHistory(auth()->id());

        return Inertia::render('Import/Index', [
            'sites' => $sites,
            'importHistory' => $importHistory,
        ]);
    }

    public function importUmami(Request $request)
    {
        $request->validate([
            'dry_run' => 'nullable|in:true,false,0,1',
        ]);

        // Gérer l'upload HTTP
        if ($request->hasFile('sql_file')) {
            $request->validate([
                'sql_file' => 'required|file|max:2048', // 2GB max
            ]);

            // Pour l'upload HTTP, on sauvegarde d'abord le fichier puis on utilise importUmamiFromFtp
            $file = $request->file('sql_file');
            $fileName = uniqid() . '_' . $file->getClientOriginalName();
            $file->move(storage_path('app/imports'), $fileName);
            
            $result = $this->importService->importUmamiFromFtp($fileName, $request->boolean('dry_run'));
        }
        // Gérer les fichiers FTP
        elseif ($request->has('ftp_file')) {
            $request->validate([
                'ftp_file' => 'required|string',
            ]);

            $result = $this->importService->importUmamiFromFtp($request->ftp_file, $request->boolean('dry_run'));
        }
        else {
            return back()->withErrors(['file' => 'No file provided']);
        }

        if ($result['success']) {
            return back()->with('success', $result['message'])->with('job_queued', $result['job_queued'] ?? false);
        } else {
            return back()->withErrors(['import' => $result['message']]);
        }
    }

    public function getFtpFiles()
    {
        $importsPath = 'imports';
        $files = [];

        // Utiliser le disque local et vérifier le chemin complet
        $fullPath = storage_path('app/' . $importsPath);
        
        if (is_dir($fullPath)) {
            $fileList = scandir($fullPath);
            
            foreach ($fileList as $file) {
                if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                    continue;
                }
                
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array($extension, ['sql', 'gz'])) {
                    $filePath = $importsPath . '/' . $file;
                    $fullFilePath = storage_path('app/' . $filePath);
                    
                    $files[] = [
                        'name' => $file,
                        'path' => $filePath,
                        'size' => file_exists($fullFilePath) ? filesize($fullFilePath) : 0,
                        'modified' => file_exists($fullFilePath) ? date('Y-m-d H:i:s', filemtime($fullFilePath)) : null,
                    ];
                }
            }
        }

        // Debug: afficher les fichiers trouvés
        \Log::info('FTP files found', [
            'path' => $importsPath,
            'full_path' => $fullPath,
            'is_dir' => is_dir($fullPath),
            'files' => $files,
            'scandir' => is_dir($fullPath) ? scandir($fullPath) : 'not a directory',
        ]);

        return response()->json($files);
    }

    public function history()
    {
        $imports = $this->importService->getImportHistory(auth()->id(), 50);
        
        return Inertia::render('Import/History', [
            'imports' => $imports,
        ]);
    }
} 