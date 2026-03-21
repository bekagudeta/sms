<?php

namespace App\Providers;

use App\Models\Schedule;
use App\Models\Student;
use App\Policies\SchedulePolicy;
use App\Policies\StudentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Schedule::class => SchedulePolicy::class,
        Student::class => StudentPolicy::class,
        // map models to policies so authorization checks work
        \App\Models\Teacher::class => \App\Policies\TeacherPolicy::class,
        \App\Models\Course::class => \App\Policies\CoursePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
