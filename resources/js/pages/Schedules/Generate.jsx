import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function Generate({ semesters, courseOfferings, teachers, rooms, timeslots, sections }) {
    const [activeTab, setActiveTab] = useState('auto');
    const { props } = usePage();
    const flash = props.flash;

    const { data: autoData, setData: setAutoData, post: autoPost, processing: autoProcessing } = useForm({
        semester_id: ''
    });

    const handleAutoGenerate = () => {
        if (!autoData.semester_id) {
            alert('Please select a semester for automatic generation');
            return;
        }

        autoPost('/schedules/generate-auto', { semester_id: autoData.semester_id }, {
            onSuccess: () => {
                // Server will redirect to /schedules with flash message
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
                                onClick={() => setActiveTab('auto')}
                                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'auto'
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                Automatic Generation
                            </button>
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
                        </nav>
                    </div>

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

                    {/* Manual Generation Tab */}
                    {activeTab === 'manual' && (
                        <ManualScheduleForm
                            semesters={semesters}
                            courseOfferings={courseOfferings}
                            teachers={teachers}
                            rooms={rooms}
                            timeslots={timeslots}
                            sections={sections}
                        />
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}

function ManualScheduleForm({ semesters, courseOfferings, teachers, rooms, timeslots, sections }) {
    console.log('ManualScheduleForm data:', {
        sectionsCount: sections?.length || 0,
        firstSection: sections?.[0],
        sectionsSample: sections?.slice(0, 10),
        allSectionIds: sections?.map(s => ({ id: s.id, name: s.section_name }))
    });
    const { data, setData, post, processing, errors, reset } = useForm({
        data: {
            semester_id: '',
            schedule: []
        }
    });

    const [newLine, setNewLine] = useState({
        course_offering_id: '',
        section_id: '',
        room_id: '',
        timeslot_id: ''
    });

    const handleAddLine = () => {
        if (!newLine.course_offering_id || !newLine.section_id || !newLine.room_id || !newLine.timeslot_id) {
            alert('Please fill all fields for manual schedule row.');
            return;
        }

        const selectedOffering = courseOfferings.find(o => o.id === parseInt(newLine.course_offering_id));
        const selectedSection = sections.find(s => s.id === parseInt(newLine.section_id));
        const selectedRoom = rooms.find(r => r.id === parseInt(newLine.room_id));
        const selectedTimeslot = timeslots.find(t => t.id === parseInt(newLine.timeslot_id));

        if (!selectedOffering || !selectedSection || !selectedRoom || !selectedTimeslot) {
            alert('Invalid data. Please verify selection.');
            return;
        }

        const scheduleItem = {
            course_offering_id: selectedOffering.id,
            section_id: selectedSection.id,
            section_name: selectedSection.section_name,
            room_id: parseInt(newLine.room_id),
            timeslot_id: parseInt(newLine.timeslot_id),
            course_name: selectedOffering.course.course_name,
            room_code: selectedRoom.room_code,
            day: selectedTimeslot.day_of_week,
            start_time: selectedTimeslot.start_time,
            end_time: selectedTimeslot.end_time
        };

        setData('data', {
            ...data.data,
            schedule: [...data.data.schedule, scheduleItem]
        });

        setNewLine({
            course_offering_id: '',
            section_id: '',
            room_id: '',
            timeslot_id: ''
        });
    };

    const handleRemove = (index) => {
        const newSchedule = data.data.schedule.filter((_, idx) => idx !== index);
        setData('data', { ...data.data, schedule: newSchedule });
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!data.data.semester_id) {
            alert('Please choose semester.');
            return;
        }

        if (data.data.schedule.length === 0) {
            alert('Add at least one manual schedule entry.');
            return;
        }

        post('/schedules/generate', {
            data: data.data,
            onSuccess: () => {
                reset();
                alert('Manual schedule entries submitted.');
            }
        });
    };

    return (
        <div>
            <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700">Semester *</label>
                <select
                    value={data.data.semester_id}
                    onChange={e => setData('data.semester_id', e.target.value)}
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
                {errors['data.semester_id'] && (
                    <p className="mt-2 text-sm text-red-600">{errors['data.semester_id']}</p>
                )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Course Offering</label>
                    <select
                        value={newLine.course_offering_id}
                        onChange={e => setNewLine({ ...newLine, course_offering_id: e.target.value })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                    >
                        <option value="">Select</option>
                        {courseOfferings?.map(offering => (
                            <option key={offering.id} value={offering.id}>
                                {offering.course.course_code} - {offering.course.course_name} ({offering.semester.name})
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Section</label>
                    <select
                        value={newLine.section_id}
                        onChange={e => setNewLine({ ...newLine, section_id: e.target.value })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                    >
                        <option value="">Select</option>
                        {sections?.filter(section => !newLine.course_offering_id || section.course_offering_id === parseInt(newLine.course_offering_id)).map(section => (
                            <option key={section.id} value={section.id}>
                                {section.section_name} (ID: {section.id})
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Room</label>
                    <select
                        value={newLine.room_id}
                        onChange={e => setNewLine({ ...newLine, room_id: e.target.value })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                    >
                        <option value="">Select</option>
                        {rooms?.map(room => (
                            <option key={room.id} value={room.id}>{room.room_code}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Timeslot</label>
                    <select
                        value={newLine.timeslot_id}
                        onChange={e => setNewLine({ ...newLine, timeslot_id: e.target.value })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                    >
                        <option value="">Select</option>
                        {timeslots?.map(ts => (
                            <option key={ts.id} value={ts.id}>
                                {ts.day_of_week} {ts.start_time} - {ts.end_time}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <button
                type="button"
                onClick={handleAddLine}
                className="mb-6 bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600"
            >
                Add Manual Schedule Row
            </button>

            {data.data.schedule.length > 0 && (
                <div className="mb-6">
                    <h4 className="font-semibold mb-2">Manual Schedule Rows</h4>
                    <div className="overflow-x-auto">
                        <table className="min-w-full bg-white border border-gray-200">
                            <thead>
                                <tr>
                                    <th className="px-3 py-2 border">Course</th>
                                    <th className="px-3 py-2 border">Section</th>
                                    <th className="px-3 py-2 border">Room</th>
                                    <th className="px-3 py-2 border">Timeslot</th>
                                    <th className="px-3 py-2 border">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.schedule.map((item, index) => (
                                    <tr key={index}>
                                        <td className="px-3 py-2 border">{item.course_name}</td>
                                        <td className="px-3 py-2 border">{item.section_name}</td>
                                        <td className="px-3 py-2 border">{item.room_code}</td>
                                        <td className="px-3 py-2 border">{item.day} {item.start_time}-{item.end_time}</td>
                                        <td className="px-3 py-2 border">
                                            <button
                                                type="button"
                                                onClick={() => handleRemove(index)}
                                                className="text-red-600 hover:text-red-800"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <button
                onClick={handleSubmit}
                disabled={processing}
                className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white py-2 px-5 rounded-md"
            >
                {processing ? 'Submitting...' : 'Submit Manual Schedule'}
            </button>
        </div>
    );
}