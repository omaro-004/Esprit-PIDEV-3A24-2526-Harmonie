<?php

namespace App\Controller\LibraryControllers;

use App\Service\LibraryServices\SuggestionsService;
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
        private Pdf                $snappy
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
    public function noteContent(int $id, int $fileId): JsonResponse
    {
        $row = $this->db->fetchAssociative(
            'SELECT originalname, mimetype, filedata FROM coursefile WHERE id = ? AND courseid = ?',
            [$fileId, $id]
        );
        if (!$row) return $this->json(['message' => 'Not found'], 404);

        $data = is_resource($row['filedata']) ? stream_get_contents($row['filedata']) : $row['filedata'];
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

    // ── PRIVATE HELPERS ───────────────────────────────────────────────────────

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
