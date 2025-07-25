<?php

namespace Gibbon\Module\CoursesAndClasses\Tables;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\DataSet;
use Gibbon\Tables\Renderer\SimpleRenderer;
use Gibbon\Module\CoursesAndClasses\Domain\CourseMaterialsGateway;
use Gibbon\Tables\Renderer\RendererInterface;

require_once __DIR__ . '/../Domain/CourseMaterialsGateway.php';
require_once  __DIR__ . '/../../moduleFunctions.php';

class CourseOverviewTable extends DataTable
{
    private Connection $connection;
    private string $guid;
    private array $resources = [];
    private array $courseIDs = [];
    private array $classMap = [];

    public function __construct(QueryCriteria $criteria, string $tableID, DataSet $data, Connection $connection, string $guid)
    {
        $renderer = new SimpleRenderer($criteria, $tableID);
        parent::__construct($renderer, $data);

        $this->connection = $connection;
        $this->guid = $guid;

        $rawCourses = iterator_to_array($data);
        $this->courseIDs = array_column($rawCourses, 'gibbonCourseID');
        $courseNames = array_column($rawCourses, 'courseName');

        $materialsGateway = new CourseMaterialsGateway($connection);
        $this->resources = $materialsGateway->selectByCourseNames($courseNames);

        $this->setTitle(__('📘 My Courses'));
        $this->addColumn('courseNameFull', __('Course'));
        $this->addColumn('courseName', __('Code'));
        $this->addColumn('materials', __('Materials'));
        $this->addColumn('classes', __('Classes'))->format(function ($row) {
            $links = array_map(function ($class) {
                $id = $class['classID'];
                $name = htmlspecialchars($class['fullName']);

                return $id
                    ? "<a href='index.php?q=/modules/Departments/department_course_class.php&gibbonCourseClassID=$id'>$name</a>"
                    : $name;
            }, $row['classes']);

            return implode(', ', $links);
        });
    }

    private function collapseByCourse(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $code = $row['courseName'] ?? '[Unknown]';
            $className = $row['className'] ?? '[Unassigned]';

            if (!isset($grouped[$code])) {
                $files = $this->resources[$code] ?? [];
                $uniqueFiles = [];
                foreach ($files as $file) {
                    $id = $file['gibbonResourceID'];
                    if (!isset($uniqueFiles[$id])) {
                        $uniqueFiles[$id] = $file;
                    }
                }

                $materials = empty($uniqueFiles)
                    ? '<em>No materials</em>'
                    : implode('<br>', array_map(function ($file) {
                        $link = getResourceLink(
                            $this->guid,
                            $file['gibbonResourceID'],
                            $file['type'],
                            $file['name'],
                            $file['content']
                        );
                        $staff = htmlspecialchars("{$file['title']} {$file['preferredName']} {$file['surname']}");
                        return $link . "<small>($staff)</small>";
                    }, $uniqueFiles));

                $grouped[$code] = [
                    'courseName' => $code,
                    'courseNameFull' => $row['courseNameFull'] ?? '[Unknown Name]',
                    'materials' => $materials,
                    'classes' => []
                ];
            }

            if (!in_array($className, $grouped[$code]['classes'])) {
                $classInfo = $this->classMap[$row['gibbonCourseID']][$className] ?? [];
                $grouped[$code]['classes'][] = [
                    'name' => $className,
                    'fullName' => $code . '.' . $className ?? $className,
                    'classID' => $classInfo['id'] ?? null,
                ];
            }
        }
        return $grouped;
    }

    public function render($rows, ?RendererInterface $renderer = null): string
    {
        //error_log("📣 render called with ".count($rows)." rows.");
        foreach ($this->courseIDs as $courseID) {
            $this->classMap[$courseID] = getClassInfoByCourse($this->connection, $courseID);
        }

        $groupedCourses = $this->collapseByCourse(iterator_to_array($rows ?? []));
        $this->withData($groupedCourses);
        if ($renderer === null) {
            $renderer = $this->getRenderer();
        }
        return parent::render($groupedCourses, $renderer);
    }
}


/* class CourseOverviewTable extends DataTable
{
    private Connection $connection;
    private string $guid;
    private array $resources = [];

    public function __construct(QueryCriteria $criteria, string $tableID, DataSet $data, Connection $connection, string $guid )
    {
        $this->connection = $connection;
        $this->guid = $guid;

        $renderer = new SimpleRenderer($criteria, $tableID);
        parent::__construct($renderer, $data);

        $rawCourses = iterator_to_array($data);
        $courseIDs = array_column($rawCourses, 'courseName');

        $materialsGateway = new CourseMaterialsGateway($connection);
        $this->resources = $materialsGateway->selectByCourseIDs($courseIDs);
        
        $this->setTitle(__('📘 My Courses')); // Optional table title

        // Full Name Column
        $this->addColumn('courseNameFull', __('Course Name'))
            ->context('primary') // Makes this visually stand out
            ->width('30%')       // Adjust as needed
            ->sortable('courseNameFull')
            ->format(function ($course) {
                return $course['courseNameFull'] ?? '[Missing]';
            });

        // Course Short Code
        $this->addColumn('courseName', __('Course Code'))
            ->context('secondary')
            ->width('20%')
            ->sortable('courseName')
            ->format(function ($course) {
                return $course['courseName'] ?? '[Missing]';
            });

        $this->addColumn('materials', __('Course Materials'))
            ->width('20%')
            ->sortable(false)
            ->format(function ($course) {
            $code = $course['courseName'] ?? null;
            $files = $this->resources[$code] ?? [];

                if (empty($files)) return '<em>No materials</em>';

                $guid = $this->guid;

        return implode('<br>', array_map(function ($file) use ($guid) {
            $link = getResourceLink(
                $guid,
                $file['gibbonResourceID'],
                $file['type'],
                $file['name'],
                $file['content'] // Changed from 'path' to 'content'
            );

            $staffName = htmlspecialchars("{$file['title']} {$file['preferredName']} {$file['surname']}");
            return $link . "<small>($staffName)</small>";
        }, $files));
            });
            
        // Class Group
        $this->addColumn('className', __('Classes'))
            ->width('10%')
            ->sortable('className')
            ->format(function ($course) {
                return $course['className'] ?? '[Missing]';
            });

    }
} */
