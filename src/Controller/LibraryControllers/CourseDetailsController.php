<?php

namespace App\Controller\LibraryControllers;

use App\Service\LibraryServices\GeminiService;
use App\Service\LibraryServices\SuggestionsService;
use Smalot\PdfParser\Parser as PdfParser;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

#[Route('/courses/{id}', name: 'app_courses_detail', requirements: ['id' => '\d+'])]
class CourseDetailsController extends AbstractController
{
    // ── Allowed upload MIME types + extensions ────────────────────────────────
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',                                                       // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // .docx
        'application/vnd.ms-powerpoint',                                            // .ppt
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',// .pptx
        'application/vnd.ms-excel',                                                 // .xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',        // .xlsx
        'text/plain',
        'text/markdown',
        'application/json',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
        'txt', 'md', 'rtfx',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
    ];

    public function __construct(
        private Connection         $db,
        private SuggestionsService $suggestionsService,
        private Pdf                $snappy,
        private GeminiService      $gemini
    ) {}

    private function getCurrentUserId(): ?int
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) return null;
        $userId = $user->getId();
        return $userId !== null ? (int) $userId : null;
    }

    private function isAllowedFile(\Symfony\Component\HttpFoundation\File\UploadedFile $file): bool
    {
        $ext  = strtolower($file->getClientOriginalExtension());
        $mime = strtolower($file->getMimeType() ?? '');
        return in_array($ext, self::ALLOWED_EXTENSIONS, true)
            || in_array($mime, self::ALLOWED_MIME_TYPES, true);
    }

    private function makeUniqueCourseFileName(int $courseId, string $desiredName, ?int $excludeFileId = null): string
    {
        $desiredName = trim($desiredName);
        if ('' === $desiredName) {
            $desiredName = 'note.rtfx';
        }

        $dotPos = strrpos($desiredName, '.');
        $base = false === $dotPos ? $desiredName : substr($desiredName, 0, $dotPos);
        $ext = false === $dotPos ? '' : substr($desiredName, $dotPos);

        $base = trim((string) $base);
        if ('' === $base) {
            $base = 'note';
        }

        $candidate = $base.$ext;
        $counter = 1;

        while ($this->courseFileNameExists($courseId, $candidate, $excludeFileId)) {
            $candidate = sprintf('%s (%d)%s', $base, $counter, $ext);
            ++$counter;
        }

        return $candidate;
    }

    private function courseFileNameExists(int $courseId, string $name, ?int $excludeFileId = null): bool
    {
        $sql = 'SELECT 1 FROM coursefile WHERE courseid = ? AND LOWER(originalname) = LOWER(?)';
        $params = [$courseId, $name];

        if (null !== $excludeFileId) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeFileId;
        }

        return (bool) $this->db->fetchOne($sql, $params);
    }

    // ── MAIN PAGE ──────────────────────────────────────────────────────────────
    #[Route('', name: '', methods: ['GET'])]
    public function index(int $id, Request $req): Response
    {
        $course = $this->db->fetchAssociative(
            'SELECT c.id, c.title, c.cover_image_path, c.is_published, c.userid,
                    s.name AS subject_name
             FROM courses c
             LEFT JOIN subject s ON s.id = c.subjectid
             WHERE c.id = ?',
            [$id]
        );

        if (!$course) throw $this->createNotFoundException('Course not found.');

        $currentUserId = $this->getCurrentUserId() ?? 0;
        $isOwner       = (int) $course['userid'] === $currentUserId && $currentUserId > 0;

        $isSaved = false;
        if (!$isOwner && $currentUserId) {
            try {
                $isSaved = (bool) $this->db->fetchOne(
                    'SELECT 1 FROM saved_courses WHERE user_id = ? AND course_id = ?',
                    [$currentUserId, $id]
                );
            } catch (\Throwable) {
                $isSaved = false;
            }
        }

        $files = $this->db->fetchAllAssociative(
            'SELECT id, originalname, mimetype, sizebytes, uploaded_at
             FROM coursefile WHERE courseid = ? ORDER BY id',
            [$id]
        );

        $subjects = $this->db->fetchAllAssociative(
            'SELECT id, name FROM subject ORDER BY name'
        );

        $origin = in_array($req->query->get('origin'), ['library', 'courses'], true)
            ? $req->query->get('origin')
            : 'courses';

        return $this->render('library/course-details.html.twig', [
            'course'    => $course,
            'isOwner'   => $isOwner,
            'isSaved'   => $isSaved,
            'files'     => $files,
            'subjects'  => $subjects,
            'origin'    => $origin,
        ]);
    }

    // ── RENAME COURSE ─────────────────────────────────────────────────────────
    #[Route('/rename', name: '_rename', methods: ['POST'])]
    public function rename(int $id, Request $req): JsonResponse
    {
        $title = trim((string) $req->request->get('title', ''));
        if ($title === '') return $this->json(['message' => 'Title required'], 400);
        $this->db->executeStatement('UPDATE courses SET title = ? WHERE id = ?', [$title, $id]);
        return $this->json(['ok' => true]);
    }

    // ── CHANGE SUBJECT ────────────────────────────────────────────────────────
    #[Route('/subject', name: '_subject', methods: ['POST'])]
    public function changeSubject(int $id, Request $req): JsonResponse
    {
        $subjectName = trim((string) $req->request->get('subject', ''));
        if ($subjectName === '') return $this->json(['message' => 'Subject required'], 400);

        $row = $this->db->fetchAssociative('SELECT id FROM subject WHERE name = ?', [$subjectName]);
        if ($row) {
            $subjectId = $row['id'];
        } else {
            $this->db->executeStatement('INSERT INTO subject (name) VALUES (?)', [$subjectName]);
            $subjectId = $this->db->lastInsertId();
        }

        $this->db->executeStatement('UPDATE courses SET subjectid = ? WHERE id = ?', [$subjectId, $id]);
        return $this->json(['ok' => true]);
    }

    // ── TOGGLE PUBLISH ────────────────────────────────────────────────────────
    #[Route('/publish', name: '_publish', methods: ['POST'])]
    public function publish(int $id, Request $req): JsonResponse
    {
        $locked = (bool) $this->db->fetchOne('SELECT admin_locked FROM courses WHERE id = ?', [$id]);
        if ($locked) {
            return $this->json([
                'message' => 'This course has been unpublished by an administrator and cannot be republished.',
            ], 403);
        }

        $published = (int) ((bool) $req->request->get('published', false));
        $this->db->executeStatement('UPDATE courses SET is_published = ? WHERE id = ?', [$published, $id]);
        return $this->json(['ok' => true, 'is_published' => $published]);
    }

    // ── UPLOAD FILES ──────────────────────────────────────────────────────────
    #[Route('/upload', name: '_upload', methods: ['POST'])]
    public function upload(int $id, Request $req): JsonResponse
    {
        $files = $req->files->get('files', []);
        if (!is_array($files)) $files = [$files];

        $uploaded = [];
        $rejected = [];

        foreach ($files as $file) {
            if (!$file) continue;

            if (!$this->isAllowedFile($file)) {
                $rejected[] = $file->getClientOriginalName();
                continue;
            }

            $bytes = file_get_contents($file->getPathname());
            if ($bytes === false) {
                return $this->json(['message' => 'Failed to read uploaded file'], 500);
            }

            $size = mb_strlen($bytes, '8bit');
            $mime = $file->getMimeType() ?? 'application/octet-stream';

            $this->db->executeStatement(
                'INSERT INTO coursefile (courseid, originalname, mimetype, sizebytes, filedata)
                 VALUES (?, ?, ?, ?, ?)',
                [$id, $file->getClientOriginalName(), $mime, $size, $bytes],
                [
                    0 => ParameterType::INTEGER,
                    1 => ParameterType::STRING,
                    2 => ParameterType::STRING,
                    3 => ParameterType::INTEGER,
                    4 => ParameterType::LARGE_OBJECT,
                ]
            );

            $uploaded[] = [
                'id'   => (int) $this->db->lastInsertId(),
                'name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $size,
            ];
        }

        return $this->json([
            'ok'       => true,
            'uploaded' => $uploaded,
            'rejected' => $rejected,
        ]);
    }

    // ── CREATE NOTE ───────────────────────────────────────────────────────────
    #[Route('/note', name: '_note', methods: ['POST'])]
    public function createNote(int $id, Request $req): JsonResponse
    {
        $name = trim((string) $req->request->get('name', 'note')) ?: 'note';
        $name = preg_replace('/\.(txt|rtfx|md)$/i', '', $name) . '.rtfx';
        $name = $this->makeUniqueCourseFileName($id, $name);

        $content = (string) $req->request->get('content', '');
        $decoded = json_decode($content, true);
        $docJson = isset($decoded['paragraphs'])
            ? $content
            : json_encode(['paragraphs' => [['text' => '', 'align' => 'left', 'font' => 'Inter', 'size' => 14]]]);

        $size = mb_strlen((string) $docJson, '8bit');

        $this->db->executeStatement(
            'INSERT INTO coursefile (courseid, originalname, mimetype, sizebytes, filedata)
             VALUES (?, ?, ?, ?, ?)',
            [$id, $name, 'application/json', $size, $docJson],
            [
                0 => ParameterType::INTEGER,
                1 => ParameterType::STRING,
                2 => ParameterType::STRING,
                3 => ParameterType::INTEGER,
                4 => ParameterType::LARGE_OBJECT,
            ]
        );

        $fileId = $this->db->lastInsertId();
        return $this->json(['ok' => true, 'id' => (int) $fileId, 'name' => $name]);
    }

    // ── LOAD NOTE CONTENT ─────────────────────────────────────────────────────
    // Handles: .rtfx (native JSON), .txt, .md, and .docx/.doc (PhpWord)
    #[Route('/note/{fileId}/content', name: '_note_content', requirements: ['fileId' => '\d+'], methods: ['GET'])]
    public function noteContent(int $id, int $fileId): ?JsonResponse
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, mimetype, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        if (!$row) return $this->json(['message' => 'Not found'], 404);

        if (is_resource($row['filedata'])) {
            rewind($row['filedata']);
            $data = stream_get_contents($row['filedata']);
        } else {
            $data = $row['filedata'];
        }

        if (empty($data)) return null;
        $name = strtolower($row['originalname'] ?? '');

        // ── Native JSON format (.rtfx) ─────────────────────────────────────
        if (str_ends_with($name, '.rtfx') || $row['mimetype'] === 'application/json') {
            $decoded = json_decode($data, true);
            if (isset($decoded['paragraphs'])) return $this->json($decoded);
            $text = trim(preg_replace('/[^\x20-\x7E\n\r\t]/', '', $data));
            return $this->json(['paragraphs' => $this->textToParagraphs($text)]);
        }

        // ── Word documents (.docx / .doc) ──────────────────────────────────
        if (str_ends_with($name, '.docx') || str_ends_with($name, '.doc')) {
            return $this->json(['paragraphs' => $this->wordToParagraphs($data, $name)]);
        }

        // ── Plain text / markdown ──────────────────────────────────────────
        $text = mb_convert_encoding($data, 'UTF-8', 'auto');
        return $this->json(['paragraphs' => $this->textToParagraphs($text)]);
    }

    // ── SAVE NOTE ─────────────────────────────────────────────────────────────
    #[Route('/note/{fileId}/save', name: '_note_save', requirements: ['fileId' => '\d+'], methods: ['POST'])]
    public function saveNote(int $id, int $fileId, Request $req): JsonResponse
    {
        $name    = trim((string) $req->request->get('name', '')) ?: null;
        $content = (string) $req->request->get('content', '');

        // Always save as .rtfx regardless of original format
        if ($name !== null) {
            $name = preg_replace('/\.(txt|rtfx|md|docx|doc)$/i', '', $name) . '.rtfx';
        }

        $decoded = json_decode($content, true);
        if (!isset($decoded['paragraphs'])) {
            $content = json_encode(['paragraphs' => [['text' => $content, 'align' => 'left', 'font' => 'Inter', 'size' => 14]]]);
        }

        $update = 'UPDATE coursefile SET filedata = ?, sizebytes = ?, mimetype = ?';
        $params = [$content, mb_strlen($content, '8bit'), 'application/json'];

        if ($name !== null) {
            $update  .= ', originalname = ?';
            $params[] = $name;
        }
        $update  .= ' WHERE id = ? AND courseid = ?';
        $params[] = $fileId;
        $params[] = $id;

        $this->db->executeStatement($update, $params);
        return $this->json(['ok' => true]);
    }

    // ── EXPORT NOTE AS PDF (KnpSnappyBundle) ─────────────────────────────────
    #[Route('/note/{fileId}/export-pdf', name: '_note_export_pdf', requirements: ['fileId' => '\d+'], methods: ['GET'])]
    public function exportNotePdf(int $id, int $fileId): Response
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        if (!$row) throw $this->createNotFoundException('Note not found.');

        $data = is_resource($row['filedata']) ? stream_get_contents($row['filedata']) : $row['filedata'];
        $doc  = json_decode($data, true);

        if (!isset($doc['paragraphs'])) {
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $data));
            $doc   = ['paragraphs' => array_map(fn($l) => ['text' => $l, 'align' => 'left', 'font' => 'Inter', 'size' => 14], $lines)];
        }

        $baseName = preg_replace('/\.(rtfx|txt|md|docx|doc)$/i', '', $row['originalname'] ?? 'note');

        $html = $this->renderView('library/note-pdf.html.twig', [
            'title'      => $baseName,
            'paragraphs' => $doc['paragraphs'],
        ]);

        $downloadName = $baseName . '.pdf';

        // 1) Try Snappy (wkhtmltopdf)
        try {
            $pdfContent = $this->snappy->getOutputFromHtml($html, [
                'encoding' => 'UTF-8',
                'print-media-type' => true,
                'margin-top' => 10,
                'margin-bottom' => 12,
                'margin-left' => 10,
                'margin-right' => 10,
            ]);

            // Validate binary signature to avoid returning plain-text errors as .pdf
            if (is_string($pdfContent) && str_starts_with($pdfContent, '%PDF')) {
                return new PdfResponse($pdfContent, $downloadName);
            }
        } catch (\Throwable) {
            // Fall through to Dompdf fallback below
        }

        // 2) Fallback: Dompdf (no wkhtmltopdf binary needed)
        $options = new DompdfOptions();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName)
        );

        return $response;
    }

    // ── PREVIEW FILE ─────────────────────────────────────────────────────────
    // Word docs → converted to HTML inline. Everything else served as-is.
    #[Route('/file/{fileId}/preview', name: '_file_preview', requirements: ['fileId' => '\d+'], methods: ['GET'])]
    public function previewFile(int $id, int $fileId): Response
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, mimetype, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        if (!$row) throw $this->createNotFoundException('File not found.');

        $data = is_resource($row['filedata']) ? stream_get_contents($row['filedata']) : $row['filedata'];
        $name = strtolower($row['originalname'] ?? '');
        $mime = $row['mimetype'] ?: 'application/octet-stream';

        // Word docs: render as HTML for the preview modal
        if (str_ends_with($name, '.docx') || str_ends_with($name, '.doc')) {
            $html = $this->wordToHtml($data, $name);
            return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        if (str_ends_with($name, '.pdf'))  $mime = 'application/pdf';
        if (str_ends_with($name, '.svg'))  $mime = 'image/svg+xml';

        $response = new Response($data);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $row['originalname'])
        );
        return $response;
    }

    // ── DOWNLOAD FILE ─────────────────────────────────────────────────────────
    #[Route('/file/{fileId}/download', name: '_file_download', requirements: ['fileId' => '\d+'], methods: ['GET'])]
    public function downloadFile(int $id, int $fileId): Response
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, mimetype, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        if (!$row) throw $this->createNotFoundException('File not found.');

        $data = is_resource($row['filedata']) ? stream_get_contents($row['filedata']) : $row['filedata'];
        $name = strtolower($row['originalname'] ?? '');
        $mime = $row['mimetype'] ?: 'application/octet-stream';

        // Correct MIME for download headers
        $mimeMap = [
            '.pdf'  => 'application/pdf',
            '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            '.doc'  => 'application/msword',
            '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            '.ppt'  => 'application/vnd.ms-powerpoint',
            '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            '.xls'  => 'application/vnd.ms-excel',
        ];
        foreach ($mimeMap as $ext => $correctMime) {
            if (str_ends_with($name, $ext)) { $mime = $correctMime; break; }
        }

        $response = new Response($data);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $row['originalname'])
        );
        return $response;
    }

    // ── RENAME FILE ───────────────────────────────────────────────────────────
    #[Route('/file/{fileId}/rename', name: '_file_rename', requirements: ['fileId' => '\d+'], methods: ['POST'])]
    public function renameFile(int $id, int $fileId, Request $req): JsonResponse
    {
        $name = trim((string) $req->request->get('name', ''));
        if ($name === '') return $this->json(['message' => 'Name required'], 400);

        $this->db->executeStatement(
            'UPDATE coursefile SET originalname = ? WHERE id = ? AND courseid = ?',
            [$name, $fileId, $id]
        );
        return $this->json(['ok' => true]);
    }

    // ── DELETE FILE ───────────────────────────────────────────────────────────
    #[Route('/file/{fileId}/delete', name: '_file_delete', requirements: ['fileId' => '\d+'], methods: ['POST'])]
    public function deleteFile(int $id, int $fileId): JsonResponse
    {
        $this->db->executeStatement(
            'DELETE FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        return $this->json(['ok' => true]);
    }

    // ── DOWNLOAD COURSE AS ZIP ────────────────────────────────────────────────
    // GET /courses/{id}/download-zip
    // Fetches every file in the course, converts notes (.rtfx / .txt / .md)
    // and Word docs (.docx / .doc) to PDF on the fly, then streams a .zip.
    #[Route('/download-zip', name: '_download_zip', methods: ['GET'])]
    public function downloadZip(int $id): Response
    {
        $course = $this->db->fetchAssociative(
            'SELECT title FROM courses WHERE id = ?',
            [$id]
        );
        if (!$course) throw $this->createNotFoundException('Course not found.');

        $files = $this->db->fetchAllAssociative(
            'SELECT id, originalname, mimetype, filedata FROM coursefile WHERE courseid = ? ORDER BY id',
            [$id]
        );

        // Build zip in a temp file
        $tmpZip = tempnam(sys_get_temp_dir(), 'course_zip_') . '.zip';
        $zip    = new \ZipArchive();
        if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new Response('Could not create zip archive.', 500);
        }

        $usedNames = [];

        foreach ($files as $file) {
            $data = is_resource($file['filedata'])
                ? stream_get_contents($file['filedata'])
                : $file['filedata'];
            $data = (string) $data;

            $origName = $file['originalname'] ?? 'file';
            $lower    = strtolower($origName);

            // ── Convert notes / Word docs → PDF ───────────────────────────
            $isNote = str_ends_with($lower, '.rtfx')
                || str_ends_with($lower, '.txt')
                || str_ends_with($lower, '.md');
            $isWord = str_ends_with($lower, '.docx') || str_ends_with($lower, '.doc');

            if ($isNote || $isWord) {
                $baseName = preg_replace('/\.(rtfx|txt|md|docx|doc)$/i', '', $origName);
                $zipName  = $this->uniqueZipName($usedNames, $baseName . '.pdf');

                try {
                    if ($isNote) {
                        $doc = json_decode($data, true);
                        if (!isset($doc['paragraphs'])) {
                            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $data));
                            $doc   = ['paragraphs' => array_map(
                                fn($l) => ['text' => $l, 'align' => 'left', 'font' => 'Inter', 'size' => 14],
                                $lines
                            )];
                        }
                        $html = $this->renderView('library/note-pdf.html.twig', [
                            'title'      => $baseName,
                            'paragraphs' => $doc['paragraphs'],
                        ]);
                    } else {
                        // Word -> HTML via PhpWord
                        $html = $this->wordToHtml($data, $lower);
                    }

                    $pdfBytes = $this->htmlToPdfBytes($html);
                    $zip->addFromString($zipName, $pdfBytes);
                } catch (\Throwable) {
                    // Conversion failed — include raw file instead
                    $rawName = $this->uniqueZipName($usedNames, $origName);
                    $zip->addFromString($rawName, $data);
                }
            } else {
                // All other files (PDF, images, spreadsheets, etc.) go in as-is
                $zipName = $this->uniqueZipName($usedNames, $origName);
                $zip->addFromString($zipName, $data);
            }
        }

        $zip->close();

        $zipBytes  = file_get_contents($tmpZip);
        @unlink($tmpZip);

        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $course['title']);
        $downloadName = $safeTitle . '.zip';

        $response = new Response($zipBytes);
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName)
        );
        return $response;
    }

    // ── GET FILE LIST FOR DOWNLOAD ───────────────────────────────────────────
    // GET /courses/{id}/download-files
    // Returns the list of files to be downloaded for progress tracking
    #[Route('/download-files', name: '_download_files', methods: ['GET'])]
    public function downloadFiles(int $id): JsonResponse
    {
        $files = $this->db->fetchAllAssociative(
            'SELECT id, originalname, mimetype, sizebytes FROM coursefile WHERE courseid = ? ORDER BY id',
            [$id]
        );

        return $this->json([
            'files' => array_map(fn($f) => [
                'id' => $f['id'],
                'name' => $f['originalname'],
                'size' => $f['sizebytes'],
            ], $files),
            'total' => count($files),
        ]);
    }

    // ── DOWNLOAD SINGLE FILE FOR ZIP ───────────────────────────────────────────
    // GET /courses/{id}/file/{fileId}/download-for-zip
    // Downloads a single file converted to PDF if needed, for building zip on client
    #[Route('/file/{fileId}/download-for-zip', name: '_file_download_for_zip', requirements: ['fileId' => '\d+'], methods: ['GET'])]
    public function downloadFileForZip(int $id, int $fileId): Response
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, mimetype, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        if (!$row) throw $this->createNotFoundException('File not found.');

        $data = is_resource($row['filedata']) ? stream_get_contents($row['filedata']) : $row['filedata'];
        $name = strtolower($row['originalname'] ?? '');

        // ── Convert notes / Word docs → PDF ───────────────────────────
        $isNote = str_ends_with($name, '.rtfx')
            || str_ends_with($name, '.txt')
            || str_ends_with($name, '.md');
        $isWord = str_ends_with($name, '.docx') || str_ends_with($name, '.doc');

        if ($isNote || $isWord) {
            $baseName = preg_replace('/\.(rtfx|txt|md|docx|doc)$/i', '', $row['originalname']);
            $downloadName = $baseName . '.pdf';

            try {
                if ($isNote) {
                    $doc = json_decode($data, true);
                    if (!isset($doc['paragraphs'])) {
                        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $data));
                        $doc   = ['paragraphs' => array_map(
                            fn($l) => ['text' => $l, 'align' => 'left', 'font' => 'Inter', 'size' => 14],
                            $lines
                        )];
                    }
                    $html = $this->renderView('library/note-pdf.html.twig', [
                        'title'      => $baseName,
                        'paragraphs' => $doc['paragraphs'],
                    ]);
                } else {
                    // Word -> HTML via PhpWord
                    $html = $this->wordToHtml($data, $name);
                }

                $pdfBytes = $this->htmlToPdfBytes($html);
                $response = new Response($pdfBytes);
                $response->headers->set('Content-Type', 'application/pdf');
                $response->headers->set(
                    'Content-Disposition',
                    $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName)
                );
                return $response;
            } catch (\Throwable) {
                // Conversion failed — return raw file
            }
        }

        // Return original file
        $mime = $row['mimetype'] ?: 'application/octet-stream';
        $mimeMap = [
            '.pdf'  => 'application/pdf',
            '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            '.doc'  => 'application/msword',
            '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            '.ppt'  => 'application/vnd.ms-powerpoint',
            '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            '.xls'  => 'application/vnd.ms-excel',
        ];
        foreach ($mimeMap as $ext => $correctMime) {
            if (str_ends_with($name, $ext)) { $mime = $correctMime; break; }
        }

        $response = new Response($data);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $row['originalname'])
        );
        return $response;
    }

    // ── SAVE TO LIBRARY (toggle) ──────────────────────────────────────────────
    #[Route('/save-to-library', name: '_save_library', methods: ['POST'])]
    public function saveToLibrary(int $id): JsonResponse
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) return $this->json(['message' => 'Not authenticated'], 401);

        $exists = $this->db->fetchOne(
            'SELECT 1 FROM saved_courses WHERE user_id = ? AND course_id = ?',
            [$userId, $id]
        );

        if ($exists) {
            $this->db->executeStatement('DELETE FROM saved_courses WHERE user_id = ? AND course_id = ?', [$userId, $id]);
            $this->db->executeStatement('UPDATE courses SET saves = GREATEST(saves - 1, 0) WHERE id = ?', [$id]);
            return $this->json(['ok' => true, 'saved' => false]);
        }

        $this->db->executeStatement('INSERT INTO saved_courses (user_id, course_id) VALUES (?, ?)', [$userId, $id]);
        $this->db->executeStatement('UPDATE courses SET saves = saves + 1 WHERE id = ?', [$id]);
        return $this->json(['ok' => true, 'saved' => true]);
    }

    // ── REPORT COURSE ─────────────────────────────────────────────────────────
    #[Route('/report', name: '_report', methods: ['POST'])]
    public function reportCourse(int $id, Request $req): JsonResponse
    {
        $reporterId = $this->getCurrentUserId();
        if (!$reporterId) return $this->json(['message' => 'Not authenticated'], 401);

        $ownerId = $this->db->fetchOne('SELECT userid FROM courses WHERE id = ?', [$id]);
        if ((int) $ownerId === $reporterId) {
            return $this->json(['message' => 'You cannot report your own course.'], 403);
        }

        $alreadyReported = $this->db->fetchOne(
            'SELECT 1 FROM course_reports WHERE reporter_id = ? AND course_id = ?',
            [$reporterId, $id]
        );
        if ($alreadyReported) {
            return $this->json(['message' => 'You have already reported this course.'], 409);
        }

        $reason  = trim((string) $req->request->get('reason', ''));
        $details = trim((string) $req->request->get('details', ''));

        if ($reason === '') return $this->json(['message' => 'A reason is required.'], 400);

        $this->db->executeStatement(
            'INSERT INTO course_reports (course_id, reporter_id, reason, details, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$id, $reporterId, $reason, $details ?: null, 'pending']
        );

        return $this->json(['ok' => true]);
    }

    // ── SUGGESTIONS ───────────────────────────────────────────────────────────
    #[Route('/suggestions', name: '_suggestions', methods: ['GET'])]
    public function suggestions(int $id): JsonResponse
    {
        $course = $this->db->fetchAssociative(
            'SELECT c.title, s.name AS subject_name
             FROM courses c
             LEFT JOIN subject s ON s.id = c.subjectid
             WHERE c.id = ?',
            [$id]
        );

        if (!$course) return $this->json(['books' => [], 'videos' => []]);

        $query = !empty($course['subject_name']) ? $course['subject_name'] : $course['title'];

        return $this->json([
            'books'  => $this->suggestionsService->fetchBooks($query, 10),
            'videos' => $this->suggestionsService->fetchVideos($query, 10),
        ]);
    }

    // ── AI META ───────────────────────────────────────────────────────────────
    // GET /courses/{id}/file/{fileId}/ai-meta
    // Returns { isPdf, canCheatSheet } — used by the frontend to decide which
    // AI buttons to show in the preview modal footer.
    #[Route('/file/{fileId}/ai-meta', name: '_file_ai_meta', requirements: ['fileId' => '\d+'], methods: ['GET'])]
    public function aiMeta(int $id, int $fileId): JsonResponse
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, mimetype, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        if (!$row) return $this->json(['message' => 'Not found'], 404);

        $name  = strtolower($row['originalname'] ?? '');
        $isPdf = str_ends_with($name, '.pdf') || $row['mimetype'] === 'application/pdf';

        if (!$isPdf) {
            return $this->json(['isPdf' => false, 'canCheatSheet' => false]);
        }

        if (is_resource($row['filedata'])) {
            rewind($row['filedata']);
            $data = stream_get_contents($row['filedata']);
        } else {
            $data = $row['filedata'];
        }

        $canCheatSheet = false;
        try {
            $parser   = new PdfParser();
            $pdf      = $parser->parseContent($data);
            $text     = $pdf->getText();
            // Count only meaningful characters (non-whitespace printable)
            $meaningful = preg_replace('/[\s\x00-\x1F\x7F]/u', '', $text ?? '');
            $canCheatSheet = mb_strlen($meaningful) >= 200;
        } catch (\Throwable) {
            // If parsing fails we still confirm it's a PDF; cheat sheet is unavailable
        }

        return $this->json(['isPdf' => true, 'canCheatSheet' => $canCheatSheet]);
    }

    // ── AI SUMMARIZE ─────────────────────────────────────────────────────────
    // POST /courses/{id}/file/{fileId}/summarize
    #[Route('/file/{fileId}/summarize', name: '_file_summarize', requirements: ['fileId' => '\d+'], methods: ['POST'])]
    public function summarize(int $id, int $fileId): JsonResponse
    {
        $result = $this->extractPdfText($id, $fileId);

        if (isset($result['error'])) {
            return match($result['error']) {
                'not_found' => $this->json(['message' => 'File not found'], 404),
                'not_pdf'   => $this->json(['message' => 'File is not a PDF'], 415),
                'empty'     => $this->json(['message' => 'Could not extract text from this PDF. It may be image-based or password-protected.'], 422),
                default     => $this->json(['message' => 'Unexpected error reading file'], 500),
            };
        }

        $text = $result['text'];

        $system = <<<SYS
You are a concise academic assistant. Produce a clear, well-structured summary of the document the user provides.
Rules:
- Write in clear, fluent prose (no excessive bullet points unless the content is naturally list-like).
- Capture the main thesis, key arguments, important facts, and any conclusions.
- Keep the summary proportional: aim for roughly 15–25% of the original length, capped at ~600 words.
- Do not add commentary, opinions, or text not supported by the document.
- Start directly with the summary; do not include a preamble like "Here is a summary of…".
SYS;

        try {
            $summary = $this->gemini->generate($system, $text);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'AI service error: ' . $e->getMessage()], 502);
        }

        return $this->json(['text' => $summary]);
    }

    // ── AI CHEAT SHEET ────────────────────────────────────────────────────────
    // POST /courses/{id}/file/{fileId}/cheat-sheet
    #[Route('/file/{fileId}/cheat-sheet', name: '_file_cheat_sheet', requirements: ['fileId' => '\d+'], methods: ['POST'])]
    public function cheatSheet(int $id, int $fileId): JsonResponse
    {
        $result = $this->extractPdfText($id, $fileId);

        if (isset($result['error'])) {
            return match($result['error']) {
                'not_found' => $this->json(['message' => 'File not found'], 404),
                'not_pdf'   => $this->json(['message' => 'File is not a PDF'], 415),
                'empty'     => $this->json(['message' => 'Could not extract text from this PDF. It may be image-based or password-protected.'], 422),
                default     => $this->json(['message' => 'Unexpected error reading file'], 500),
            };
        }

        $text = $result['text'];

        $system = <<<SYS
You are an expert study-aid creator. Transform the document the user provides into a dense, exam-ready cheat sheet.
Rules:
- Use structured sections with clear headings (##).
- Within each section use concise bullet points, short definitions, or key formulas — not prose paragraphs.
- Prioritise: core concepts, definitions, key dates/numbers, formulas, comparisons, and anything likely to appear on an exam.
- Strip all fluff; every line must earn its place.
- Do not add information not present in the document.
- Do not include a preamble — start immediately with the first section.
SYS;

        try {
            $sheet = $this->gemini->generate($system, $text);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'AI service error: ' . $e->getMessage()], 502);
        }

        return $this->json(['text' => $sheet]);
    }

    // ── PRIVATE: extract text from a PDF stored in coursefile ────────────────
    // Max characters sent to Gemini — ~15 000 chars ≈ ~4 000 tokens, well within
    // the free-tier per-request limit while covering most academic documents.
    private const AI_TEXT_LIMIT = 15000;

    /**
     * Returns ['text' => string] on success, or ['error' => string] on failure.
     * Error keys: 'not_found', 'not_pdf', 'empty'.
     *
     * FIX: previously returned ?string, conflating "file not found" and "not a PDF"
     * into the same null — making it impossible to return a meaningful HTTP status.
     * Now callers can map each error case to the correct response code.
     *
     * @return array{text: string}|array{error: string}
     */
    private function extractPdfText(int $courseId, int $fileId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, mimetype, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $courseId]
        );
        if (!$row) return ['error' => 'not_found'];

        // FIX: rewind stream before reading — Doctrine may leave the pointer at EOF
        if (is_resource($row['filedata'])) {
            rewind($row['filedata']);
            $data = stream_get_contents($row['filedata']);
        } else {
            $data = $row['filedata'];
        }

        // FIX: cast to string — some DB drivers return a resource handle or lazy
        // object instead of raw bytes; (string) forces materialisation so that the
        // %PDF header sniff below actually works.
        $data = (string) $data;

        if ($data === '') return ['error' => 'not_found'];

        $name = strtolower($row['originalname'] ?? '');

        // FIX: also sniff the binary %PDF header so files stored with a wrong
        // MIME type or a renamed extension are still processed correctly.
        $looksLikePdf = str_ends_with($name, '.pdf')
            || $row['mimetype'] === 'application/pdf'
            || str_starts_with($data, '%PDF');

        // FIX: return a typed error instead of null so callers can distinguish
        // "file not a PDF" (415) from "file not found" (404).
        if (!$looksLikePdf) return ['error' => 'not_pdf'];

        try {
            $parser = new PdfParser();
            $pdf    = $parser->parseContent($data);
            $text   = $pdf->getText() ?: '';
            file_put_contents('D:/web/pdf_debug.txt', 'Length: ' . strlen($text) . "\n" . substr($text, 0, 500));
        } catch (\Throwable $e) {
            file_put_contents('D:/web/pdf_debug.txt', 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ['error' => 'empty'];
        }

        // Collapse excessive whitespace so we don't waste tokens on blank lines
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = preg_replace('/(\r?\n){3,}/', "\n\n", $text);
        $text = trim($text);

        if ($text === '') return ['error' => 'empty'];

        // Hard cap — truncate cleanly at a word boundary
        if (mb_strlen($text) > self::AI_TEXT_LIMIT) {
            $text = mb_substr($text, 0, self::AI_TEXT_LIMIT);
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace !== false) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            $text .= "\n\n[Document truncated for AI processing]";
        }

        return ['text' => $text];
    }


    // ── PRIVATE HELPERS ───────────────────────────────────────────────────────

    /**
     * Convert an HTML string to PDF bytes using Snappy (wkhtmltopdf) with
     * Dompdf as a fallback — mirrors the logic in exportNotePdf().
     */
    private function htmlToPdfBytes(string $html): string
    {
        try {
            $pdfContent = $this->snappy->getOutputFromHtml($html, [
                'encoding'         => 'UTF-8',
                'print-media-type' => true,
                'margin-top'       => 10,
                'margin-bottom'    => 12,
                'margin-left'      => 10,
                'margin-right'     => 10,
            ]);
            if (is_string($pdfContent) && str_starts_with($pdfContent, '%PDF')) {
                return $pdfContent;
            }
        } catch (\Throwable) {
            // fall through to Dompdf
        }

        $options = new DompdfOptions();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Return a filename that does not clash with already-used zip entry names.
     * Appends " (1)", " (2)", … before the extension when a conflict exists.
     *
     * @param array<string,true> $used  Pass-by-reference map of already-used names.
     */
    private function uniqueZipName(array &$used, string $desired): string
    {
        $desired = ltrim($desired, '/\\');

        if (!isset($used[$desired])) {
            $used[$desired] = true;
            return $desired;
        }

        $dot  = strrpos($desired, '.');
        $base = $dot !== false ? substr($desired, 0, $dot) : $desired;
        $ext  = $dot !== false ? substr($desired, $dot)    : '';

        $counter = 1;
        do {
            $candidate = sprintf('%s (%d)%s', $base, $counter, $ext);
            $counter++;
        } while (isset($used[$candidate]));

        $used[$candidate] = true;
        return $candidate;
    }

    /**
     * Convert a plain text string to our internal paragraph JSON format.
     */
    private function textToParagraphs(string $text): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $paragraphs = array_map(fn($l) => [
            'text'  => $l,
            'align' => 'left',
            'font'  => 'Inter',
            'size'  => 14,
        ], $lines);
        return $paragraphs ?: [['text' => '', 'align' => 'left', 'font' => 'Inter', 'size' => 14]];
    }

    /**
     * Parse a .docx/.doc binary blob and return our internal paragraph format
     * so it can be opened and edited in the note editor.
     */
    private function wordToParagraphs(string $blob, string $filename): array
    {
        $paragraphs = [];
        try {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $tmp = tempnam(sys_get_temp_dir(), 'phpdocx_') . '.' . $ext;
            file_put_contents($tmp, $blob);

            $type   = strtolower($ext) === 'docx' ? 'Word2007' : 'MsDoc';
            $reader = WordIOFactory::createReader($type);
            $doc    = $reader->load($tmp);
            @unlink($tmp);

            foreach ($doc->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text  = '';
                    $align = 'left';

                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun
                        || $element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
                        $pStyle = $element->getParagraphStyle();
                        if (is_object($pStyle)) {
                            $a = $pStyle->getAlignment();
                            if (in_array($a, ['center', 'right', 'justify'], true)) $align = $a;
                        }
                        foreach ($element->getElements() as $child) {
                            if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $child->getText();
                            } elseif ($child instanceof \PhpOffice\PhpWord\Element\TextBreak) {
                                $text .= "\n";
                            }
                        }
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                        $text = $element->getText();
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextBreak
                        || $element instanceof \PhpOffice\PhpWord\Element\PageBreak) {
                        $paragraphs[] = ['text' => '', 'align' => 'left', 'font' => 'Inter', 'size' => 14];
                        continue;
                    } else {
                        continue; // skip tables, images, drawings
                    }

                    $paragraphs[] = [
                        'text'  => $text,
                        'align' => $align,
                        'font'  => 'Inter',
                        'size'  => 14,
                    ];
                }
            }
        } catch (\Throwable) {
            // PhpWord failed — fall back to raw printable text extraction
            $paragraphs = $this->textToParagraphs(
                trim(preg_replace('/[^\x20-\x7E\n\r\t]/', '', $blob))
            );
        }

        return $paragraphs ?: [['text' => '', 'align' => 'left', 'font' => 'Inter', 'size' => 14]];
    }

    /**
     * Convert a .docx/.doc blob to a self-contained HTML string
     * for rendering inside the preview modal iframe.
     */
    private function wordToHtml(string $blob, string $filename): string
    {
        try {
            $ext    = pathinfo($filename, PATHINFO_EXTENSION);
            $tmp    = tempnam(sys_get_temp_dir(), 'phpdocx_') . '.' . $ext;
            file_put_contents($tmp, $blob);

            $type   = strtolower($ext) === 'docx' ? 'Word2007' : 'MsDoc';
            $reader = WordIOFactory::createReader($type);
            $doc    = $reader->load($tmp);
            @unlink($tmp);

            $tmpOut = tempnam(sys_get_temp_dir(), 'phpdocx_out_') . '.html';
            $writer = WordIOFactory::createWriter($doc, 'HTML');
            $writer->save($tmpOut);

            $html = file_get_contents($tmpOut);
            @unlink($tmpOut);

            return '<!DOCTYPE html><html><head><meta charset="UTF-8">
                <style>
                    body { font-family: Inter, Arial, sans-serif; font-size: 13px;
                           line-height: 1.7; color: #111; padding: 24px 32px; margin: 0; }
                    table { border-collapse: collapse; width: 100%; margin: 12px 0; }
                    td, th { border: 1px solid #e5e7eb; padding: 6px 10px; }
                    img { max-width: 100%; height: auto; }
                    p { margin: 0 0 6px; }
                </style></head><body>' . $html . '</body></html>';

        } catch (\Throwable) {
            return '<html><body style="font-family:sans-serif;padding:32px;color:#6b7280;">
                        <p>Preview not available for this file. Please use the Download button.</p>
                    </body></html>';
        }
    }
}
