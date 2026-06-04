<?php
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

$item = \App\Models\SectionTeacher::with(['section.courseOffering.course', 'section.courseOffering.semester', 'teacher'])->first();

echo "Full item JSON:\n";
echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

echo "\n\nSection keys:\n";
echo json_encode(array_keys($item->section->toArray()), JSON_PRETTY_PRINT) . "\n";

echo "\n\nCourseOffering keys:\n";
echo json_encode(array_keys($item->section->courseOffering->toArray()), JSON_PRETTY_PRINT) . "\n";
?>
