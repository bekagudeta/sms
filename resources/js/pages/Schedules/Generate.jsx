import React, { useState } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function Generate({ courses, teachers, rooms, semesters }) {
    const [schedule, setSchedule] = useState([]);
    const [activeTab, setActiveTab] = useState('manual');
    const [errors, setErrors] = useState({});
    const { data, setData, post, processing } = useForm({
        course_id: '',
        teacher_id: '',
        room: '',
        day: '',
        start_time: '',
        end_time: '',
        semester_id: ''
    });

    const { data: autoData, setData: setAutoData, post: autoPost, processing: autoProcessing } = useForm({
        semester_id: ''
    });

    const { props } = usePage();
    const flash = props.flash;

    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    const validateScheduleItem = (item) => {
        const newErrors = {};
        
        if (!item.course_id) newErrors.course_id = 'Course is required';
        if (!item.teacher_id) newErrors.teacher_id = 'Teacher is required';
        if (!item.room) newErrors.room = 'Room is required';
        if (!item.day) newErrors.day = 'Day is required';
        if (!item.start_time) newErrors.start_time = 'Start time is required';
        if (!item.end_time) newErrors.end_time = 'End time is required';
        
        // Validate time format
        if (item.start_time && !/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(item.start_time)) {
            newErrors.start_time = 'Invalid time format (HH:MM)';
        }
        if (item.end_time && !/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(item.end_time)) {
            newErrors.end_time = 'Invalid time format (HH:MM)';
        }
        
        // Validate time logic
        if (item.start_time && item.end_time && item.start_time >= item.end_time) {
            newErrors.end_time = 'End time must be after start time';
        }
        
        return newErrors;
    };

    const handleAddToSchedule = () => {
        const validationErrors = validateScheduleItem(data);
        
        if (Object.keys(validationErrors).length > 0) {
            setErrors(validationErrors);
            return;
        }
        
        const course = courses.find(c => c.id == data.course_id);
        const teacher = teachers.find(t => t.id == data.teacher_id);
        
        const newScheduleItem = {
            ...data,
            course_name: course?.course_name || 'Unknown Course',
            teacher_name: teacher?.full_name || 'Unknown Teacher',
            id: Date.now()
        };
        
        setSchedule([...schedule, newScheduleItem]);
        setErrors({});
        
        // Reset form fields except semester
        setData({
            ...data,
            course_id: '',
            teacher_id: '',
            room: '',
            day: '',
            start_time: '',
            end_time: ''
        });
    };

    const handleRemoveFromSchedule = (id) => {
        setSchedule(schedule.filter(item => item.id !== id));
    };

    const handleGenerate = () => {
        if (schedule.length === 0) {
            alert('Please add at least one schedule item before generating');
            return;
        }

        if (!data.semester_id) {
            alert('Please select a semester before generating');
            return;
        }

        // Validate all items before submission
        let hasErrors = false;
        const allErrors = {};
        
        schedule.forEach((item, index) => {
            const itemErrors = validateScheduleItem(item);
            if (Object.keys(itemErrors).length > 0) {
                hasErrors = true;
                allErrors[`item_${index}`] = itemErrors;
            }
        });
        
        if (hasErrors) {
            alert('Please fix validation errors before generating schedules');
            return;
        }
        
        // Submit the entire schedule
        router.post('/schedules/generate', {
            data: { 
                schedule: schedule.map(item => ({
                    course_id: item.course_id,
                    teacher_id: item.teacher_id,
                    room: item.room,
                    day: item.day,
                    start_time: item.start_time,
                    end_time: item.end_time
                })), 
                semester_id: data.semester_id 
            }
        }, {
            onSuccess: (page) => {
                setSchedule([]);
                setErrors({});
                // Show success message from flash session
                if (page.props.flash?.success) {
                    // Success message will be shown automatically
                }
            },
            onError: (errors) => {
                console.error('Generation errors:', errors);
                setErrors(errors);
                alert('Error generating schedule: ' + Object.values(errors).join(', '));
            },
            preserveState: false
        });
    };

    const handleAutoGenerate = () => {
        if (!autoData.semester_id) {
            alert('Please select a semester for automatic generation');
            return;
        }

        // Pass semester_id in request payload and let Inertia handle redirection/flash on server response.
        autoPost('/schedules/generate-auto', { semester_id: autoData.semester_id }, {
            onSuccess: () => {
                // After successful generation, server redirects to /schedules with flash message.
                // No need to force client-side navigation here.
            },
            onError: (errors) => {
                console.error('Auto generation errors:', errors);
                alert('Error generating automatic schedule: ' + Object.values(errors).join(', '));
            }
        });
    };

    return (
        <DashboardLayout>
            <Head title="Generate Schedule" />
            
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <h2 className="text-2xl font-bold mb-6">Schedule Generation</h2>

                    {flash?.success && (
                        <div className="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                            {flash.error}
                        </div>
                    )}

                    {/* Tab Navigation */}
                    <div className="border-b border-gray-200 mb-6">
                        <nav className="-mb-px flex space-x-8">
                            <button
                                onClick={() => setActiveTab('manual')}
                                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'manual'
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                Manual Generation
                            </button>
                            <button
                                onClick={() => setActiveTab('auto')}
                                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'auto'
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                Automatic Generation
                            </button>
                        </nav>
                    </div>

                    {/* Manual Generation Tab */}
                    {activeTab === 'manual' && (
                        <div>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700">Semester *</label>
                                <select
                                    value={data.semester_id}
                                    onChange={e => setData('semester_id', e.target.value)}
                                    className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                        errors.semester_id ? 'border-red-500' : ''
                                    }`}
                                    required
                                >
                                    <option value="">Select Semester</option>
                                    <option value="1">1st Semester</option>
                                    <option value="2">2nd Semester</option>
                                </select>
                                {errors.semester_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.semester_id}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Course *</label>
                                    <select
                                        value={data.course_id}
                                        onChange={e => setData('course_id', e.target.value)}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                            errors.course_id ? 'border-red-500' : ''
                                        }`}
                                    >
                                        <option value="">Select Course</option>
                                        {courses.map(course => (
                                            <option key={course.id} value={course.id}>
                                                {course.course_code} - {course.course_name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.course_id && (
                                        <p className="mt-1 text-sm text-red-600">{errors.course_id}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Teacher *</label>
                                    <select
                                        value={data.teacher_id}
                                        onChange={e => setData('teacher_id', e.target.value)}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                            errors.teacher_id ? 'border-red-500' : ''
                                        }`}
                                    >
                                        <option value="">Select Teacher</option>
                                        {teachers.map(teacher => (
                                            <option key={teacher.id} value={teacher.id}>
                                                {teacher.full_name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.teacher_id && (
                                        <p className="mt-1 text-sm text-red-600">{errors.teacher_id}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Room *</label>
                                    <select
                                        value={data.room}
                                        onChange={e => setData('room', e.target.value)}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                            errors.room ? 'border-red-500' : ''
                                        }`}
                                    >
                                        <option value="">Select Room</option>
                                        {rooms.map(room => (
                                            <option key={room.id} value={room.room_code}>
                                                {room.room_code} (Capacity: {room.capacity})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.room && (
                                        <p className="mt-1 text-sm text-red-600">{errors.room}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Day *</label>
                                    <select
                                        value={data.day}
                                        onChange={e => setData('day', e.target.value)}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                            errors.day ? 'border-red-500' : ''
                                        }`}
                                    >
                                        <option value="">Select Day</option>
                                        {days.map(day => (
                                            <option key={day} value={day}>{day}</option>
                                        ))}
                                    </select>
                                    {errors.day && (
                                        <p className="mt-1 text-sm text-red-600">{errors.day}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Start Time *</label>
                                    <input
                                        type="time"
                                        value={data.start_time}
                                        onChange={e => setData('start_time', e.target.value)}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                            errors.start_time ? 'border-red-500' : ''
                                        }`}
                                    />
                                    {errors.start_time && (
                                        <p className="mt-1 text-sm text-red-600">{errors.start_time}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">End Time *</label>
                                    <input
                                        type="time"
                                        value={data.end_time}
                                        onChange={e => setData('end_time', e.target.value)}
                                        className={`mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                            errors.end_time ? 'border-red-500' : ''
                                        }`}
                                    />
                                    {errors.end_time && (
                                        <p className="mt-1 text-sm text-red-600">{errors.end_time}</p>
                                    )}
                                </div>
                            </div>

                            <div className="mb-6">
                                <button
                                    type="button"
                                    onClick={handleAddToSchedule}
                                    disabled={processing}
                                    className="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-medium py-2 px-6 rounded-md transition-colors duration-200"
                                >
                                    Add to Schedule
                                </button>
                            </div>

                            {schedule.length > 0 && (
                                <div className="mb-6">
                                    <h3 className="text-lg font-semibold mb-4">Schedule Preview ({schedule.length} items)</h3>
                                    <div className="bg-gray-50 rounded-lg overflow-hidden">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-100">
                                                <tr>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Course
                                                    </th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Teacher
                                                    </th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Room
                                                    </th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Day
                                                    </th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Time
                                                    </th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Action
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {schedule.map((item) => (
                                                    <tr key={item.id} className="hover:bg-gray-50">
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            {item.course_name}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {item.teacher_name}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {item.room}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {item.day}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {item.start_time} - {item.end_time}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                            <button
                                                                onClick={() => handleRemoveFromSchedule(item.id)}
                                                                className="text-red-600 hover:text-red-900 font-medium"
                                                            >
                                                                Remove
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div className="mt-6 flex items-center space-x-4">
                                        <button
                                            onClick={handleGenerate}
                                            disabled={processing}
                                            className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-medium py-2 px-6 rounded-md transition-colors duration-200"
                                        >
                                            {processing ? 'Generating...' : 'Generate Schedule'}
                                        </button>
                                        <button
                                            onClick={() => setSchedule([])}
                                            className="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-6 rounded-md transition-colors duration-200"
                                        >
                                            Clear All
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Automatic Generation Tab */}
                    {activeTab === 'auto' && (
                        <div>
                            <div className="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                                <h3 className="text-lg font-semibold text-blue-800 mb-2">Automatic Schedule Generation</h3>
                                <p className="text-blue-700 mb-4">
                                    The system will automatically generate schedules based on professional constraints including:
                                </p>
                                <ul className="list-disc list-inside text-blue-700 space-y-1">
                                    <li>No room or teacher conflicts</li>
                                    <li>Room capacity and type requirements</li>
                                    <li>Teacher qualifications and workload limits</li>
                                    <li>Student group conflict avoidance</li>
                                    <li>Optimized timing preferences</li>
                                </ul>
                            </div>

                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700">Semester *</label>
                                <select
                                    value={autoData.semester_id}
                                    onChange={e => setAutoData('semester_id', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                    <option value="">Select Semester</option>
                                    {semesters?.map(semester => (
                                        <option key={semester.id} value={semester.id}>
                                            {semester.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="mb-6">
                                <button
                                    onClick={handleAutoGenerate}
                                    disabled={autoProcessing}
                                    className="bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white font-medium py-3 px-6 rounded-md transition-colors duration-200"
                                >
                                    {autoProcessing ? 'Generating...' : 'Generate Automatic Schedule'}
                                </button>
                            </div>

                            <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                                <h4 className="font-semibold text-yellow-800 mb-2">Important Notes:</h4>
                                <ul className="list-disc list-inside text-yellow-700 space-y-1">
                                    <li>All existing schedules for the selected semester will be replaced</li>
                                    <li>Make sure you have sufficient rooms, teachers, and timeslots configured</li>
                                    <li>Courses without qualified teachers or suitable rooms may cause conflicts</li>
                                    <li>Review the generated schedule and resolve any conflicts manually if needed</li>
                                </ul>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}