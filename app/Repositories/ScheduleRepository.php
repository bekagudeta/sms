<?php



namespace App\Repositories;



use App\Models\Schedule;

use Illuminate\Support\Collection;



class ScheduleRepository

{

    public function getAll(): Collection

    {

        return Schedule::with(['course', 'teacher', 'room', 'timeslot', 'semester'])

                      ->get();

    }

    public function paginate(int $perPage = 10, ?int $semesterId = null)
    {
        return Schedule::with(['course', 'teacher', 'room', 'timeslot', 'semester'])
                      ->when($semesterId, fn($query) => $query->where('semester_id', $semesterId))
                      ->orderBy('day')
                      ->orderBy('start_time')
                      ->paginate($perPage)
                      ->withQueryString();
    }

    public function paginateByTeacher(int $teacherId, int $perPage = 10)
    {
        return Schedule::with(['course', 'room', 'timeslot', 'semester'])
                      ->where('teacher_id', $teacherId)
                      ->orderBy('day')
                      ->orderBy('start_time')
                      ->paginate($perPage)
                      ->withQueryString();
    }

    public function paginateBySemester(int $semesterId, int $perPage = 10)
    {
        return Schedule::with(['course', 'teacher', 'room', 'timeslot', 'semester'])
                      ->where('semester_id', $semesterId)
                      ->orderBy('day')
                      ->orderBy('start_time')
                      ->paginate($perPage)
                      ->withQueryString();
    }


    public function findById($id): ?Schedule

    {

        return Schedule::with(['course', 'teacher', 'room', 'timeslot', 'semester'])

                      ->find($id);

    }



    public function create(array $data): Schedule

    {

        return Schedule::create($data);

    }



    public function update($id, array $data): bool

    {

        $schedule = $this->findById($id);

        return $schedule ? $schedule->update($data) : false;

    }



    public function delete($id): bool

    {

        $schedule = $this->findById($id);

        return $schedule ? $schedule->delete() : false;

    }



    public function getBySemester($semesterId): Collection

    {

        return Schedule::where('semester_id', $semesterId)

                      ->with(['course', 'teacher', 'room', 'timeslot'])

                      ->get();

    }



    public function getByTeacher($teacherId): Collection

    {

        return Schedule::where('teacher_id', $teacherId)

                      ->with(['course', 'room', 'timeslot', 'semester'])

                      ->get();

    }



    public function getByRoom($roomId): Collection

    {

        return Schedule::where('room_id', $roomId)

                      ->with(['course', 'teacher', 'timeslot', 'semester'])

                      ->get();

    }



    public function checkConflicts($roomId, $timeslotId, $semesterId, $excludeId = null): bool

    {

        $query = Schedule::where('room_id', $roomId)

                        ->where('timeslot_id', $timeslotId)

                        ->where('semester_id', $semesterId);

        

        if ($excludeId) {

            $query->where('id', '!=', $excludeId);

        }

        

        return $query->exists();

    }

}