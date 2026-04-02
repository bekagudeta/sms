import React, { useState, useRef } from "react";
import { useForm } from "@inertiajs/react";

export default function ImportModal({
    isOpen,
    onClose,
    entityType,
    config,
    onSuccess,
}) {
    const fileInputRef = useRef(null);
    const [dragActive, setDragActive] = useState(false);
    const [previewData, setPreviewData] = useState([]);
    const [step, setStep] = useState(1); // 1: file selection, 2: preview, 3: processing

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
        entity_type: entityType,
        mappings: {},
        skip_header: true,
    });

    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFile(e.dataTransfer.files[0]);
        }
    };

    const handleFileSelect = (e) => {
        if (e.target.files && e.target.files[0]) {
            handleFile(e.target.files[0]);
        }
    };

    const normalizeColumnName = (value) => {
        return String(value || "")
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "_")
            .replace(/^_+|_+$/g, "");
    };

    const inferMappings = (headers) => {
        const normalizedHeaders = headers.map(normalizeColumnName);
        const mappings = {};

        const allFields = [
            ...(config.requiredColumns || []),
            ...(config.optionalColumns || []),
        ];

        const aliasMap = {
            department_id: [
                "department_id",
                "department",
                "department_code",
                "department name",
                "dept",
                "dept_id",
            ],
            hours_per_week: [
                "hours_per_week",
                "hours per week",
                "hours",
                "weekly_hours",
                "prerequisites",
            ],
        };

        allFields.forEach((field) => {
            const normalizedField = normalizeColumnName(field);

            // exact match
            let foundIndex = normalizedHeaders.findIndex(
                (h) => h === normalizedField,
            );

            // alias override
            if (foundIndex === -1 && aliasMap[field]) {
                foundIndex = normalizedHeaders.findIndex((h) =>
                    aliasMap[field].includes(h),
                );
            }

            // direct substring match
            if (foundIndex === -1) {
                foundIndex = normalizedHeaders.findIndex(
                    (h) =>
                        h.includes(normalizedField) ||
                        normalizedField.includes(h),
                );
            }

            // fallback id mapping for any _id field
            if (foundIndex === -1 && normalizedField.endsWith("_id")) {
                const base = normalizedField.replace(/_id$/, "");
                foundIndex = normalizedHeaders.findIndex(
                    (h) =>
                        h === `${base}_id` ||
                        h === `${base}_code` ||
                        h === `${base}_name` ||
                        h === base,
                );
            }

            if (foundIndex !== -1) {
                mappings[field] = String(foundIndex);
            }
        });

        return mappings;
    };

    const handleFile = async (file) => {
        if (!file) return;

        const validTypes = [".csv", ".xlsx", ".xls"];
        const fileExtension = "." + file.name.split(".").pop().toLowerCase();

        if (!validTypes.includes(fileExtension)) {
            alert("Please upload a CSV or Excel file");
            return;
        }

        setData("file", file);

        const formData = new FormData();
        formData.append("file", file);
        formData.append("entity_type", entityType);
        formData.append("skip_header", data.skip_header ? "1" : "0");
        config.requiredColumns?.forEach((field) =>
            formData.append("required_columns[]", field),
        );
        config.optionalColumns?.forEach((field) =>
            formData.append("optional_columns[]", field),
        );

        try {
            const response = await fetch(route("import.preview"), {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]',
                    ).content,
                },
                body: formData,
            });

            if (!response.ok) {
                throw new Error("Failed to read import file preview.");
            }

            const result = await response.json();

            let finalMappings = {
                ...inferMappings(result.headers || []),
                ...(result.mapping || {}),
            };

            // Auto-assign unmapped fields from available columns
            const allFields = [
                ...new Set([
                    ...(config.requiredColumns || []),
                    ...(config.optionalColumns || []),
                ]),
            ];

            const mappedIndices = new Set(
                Object.values(finalMappings)
                    .filter((v) => v !== undefined && v !== null && v !== "")
                    .map((v) => Number(v)),
            );

            const remainingIndices = (result.headers || [])
                .map((_, i) => i)
                .filter((i) => !mappedIndices.has(i));

            const unmappedFields = allFields.filter(
                (field) => finalMappings[field] === undefined,
            );

            // Try fuzzy matching on the remaining fields again, then assign sequentially
            unmappedFields.forEach((field) => {
                const normalizedField = normalizeColumnName(field);

                if (!remainingIndices.length) return;

                let match = (result.headers || []).findIndex(
                    (header, index) =>
                        remainingIndices.includes(index) &&
                        normalizeColumnName(header).includes(normalizedField),
                );

                if (match === -1) {
                    // last resort: map next available index
                    match = remainingIndices[0];
                }

                if (match !== -1 && match !== undefined) {
                    finalMappings[field] = String(match);
                    const removeIndex = remainingIndices.indexOf(match);
                    if (removeIndex > -1) {
                        remainingIndices.splice(removeIndex, 1);
                    }
                }
            });

            // If section-teachers import has one teacher field mapped, mirror to allow either teacher_id or teacher_ids
            if (entityType === "section-teachers") {
                if (finalMappings.teacher_ids && !finalMappings.teacher_id) {
                    finalMappings.teacher_id = finalMappings.teacher_ids;
                } else if (finalMappings.teacher_id && !finalMappings.teacher_ids) {
                    finalMappings.teacher_ids = finalMappings.teacher_id;
                }
            }

            setPreviewData({
                headers: result.headers || [],
                data: result.rows || [],
            });
            setData("mappings", finalMappings);
            setStep(2);
        } catch (error) {
            console.error("Error reading file:", error);
            alert("Error reading file. Please check the file format or size.");
        }
    };

    const toTimeString = (value) => {
        if (value === null || value === undefined || value === "") {
            return "";
        }

        const asNumber = Number(value);
        if (!Number.isNaN(asNumber)) {
            // Excel stores time as fraction of day
            const totalSeconds = Math.round(asNumber * 86400);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
        }

        // Try parsing ISO / human times
        const normalized = String(value).trim();
        const matches = normalized.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
        if (matches) {
            let h = Number(matches[1]);
            const m = Number(matches[2]);
            const s = Number(matches[3] || "0");
            const ampm = matches[4] ? matches[4].toUpperCase() : null;
            if (ampm === "PM" && h < 12) h += 12;
            if (ampm === "AM" && h === 12) h = 0;
            return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
        }

        return normalized;
    };

    const handleMappingChange = (requiredColumn, fileColumn) => {
        setData("mappings", {
            ...data.mappings,
            [requiredColumn]: fileColumn,
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append("file", data.file);
        formData.append("entity_type", data.entity_type);
        formData.append("skip_header", data.skip_header ? "1" : "0");

        Object.entries(data.mappings).forEach(([key, value]) => {
            formData.append(`mappings[${key}]`, value);
        });

        post("/import/bulk", {
            data: formData,
            onSuccess: () => {
                onSuccess();
                handleClose();
            },
            onError: (errors) => {
                console.error("Import errors:", errors);
                alert("Import failed. Please check the file and mappings.");
            },
        });
    };

    const handleClose = () => {
        setStep(1);
        setPreviewData([]);
        setData("file", null);
        setData("mappings", {});
        setData("skip_header", true);
        reset();
        onClose();
    };

    const renderFileSelection = () => (
        <div>
            <div
                className={`relative border-2 border-dashed rounded-lg p-6 text-center ${
                    dragActive
                        ? "border-blue-400 bg-blue-50"
                        : "border-gray-300"
                }`}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
            >
                <input
                    ref={fileInputRef}
                    type="file"
                    className="hidden"
                    accept=".csv,.xlsx,.xls"
                    onChange={handleFileSelect}
                />

                <div className="space-y-4">
                    <div className="mx-auto h-12 w-12 text-gray-400">
                        <svg
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 48 48"
                        >
                            <path
                                d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </div>

                    <div>
                        <p className="text-lg font-medium text-gray-900">
                            Drop your file here, or{" "}
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="text-blue-600 hover:text-blue-500"
                            >
                                browse
                            </button>
                        </p>
                        <p className="text-sm text-gray-500 mt-1">
                            Supports CSV, Excel files (.csv, .xlsx, .xls)
                        </p>
                    </div>
                </div>
            </div>

            <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 className="font-medium text-gray-900 mb-2">
                    Required Columns:
                </h4>
                <div className="text-sm text-gray-600">
                    {config.requiredColumns.map((col) => (
                        <span
                            key={col}
                            className="inline-block px-2 py-1 bg-blue-100 text-blue-800 rounded mr-2 mb-2"
                        >
                            {col}
                        </span>
                    ))}
                </div>
                {config.optionalColumns.length > 0 && (
                    <>
                        <h4 className="font-medium text-gray-900 mt-4 mb-2">
                            Optional Columns:
                        </h4>
                        <div className="text-sm text-gray-600">
                            {config.optionalColumns.map((col) => (
                                <span
                                    key={col}
                                    className="inline-block px-2 py-1 bg-gray-200 text-gray-800 rounded mr-2 mb-2"
                                >
                                    {col}
                                </span>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </div>
    );

    const renderPreview = () => (
        <div>
            <div className="mb-4">
                <h3 className="text-lg font-medium text-gray-900">
                    File Preview
                </h3>
                <p className="text-sm text-gray-500">
                    Map your file columns to the required fields
                </p>
            </div>

            <div className="mb-6">
                <label className="flex items-center">
                    <input
                        type="checkbox"
                        checked={data.skip_header}
                        onChange={(e) =>
                            setData("skip_header", e.target.checked)
                        }
                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <span className="ml-2 text-sm text-gray-700">
                        Skip first row (header)
                    </span>
                </label>
            </div>

            <div className="space-y-4">
                {[
                    ...new Set([
                        ...(config.requiredColumns || []),
                        ...(config.optionalColumns || []),
                    ]),
                ].map((col) => {
                    const required = (config.requiredColumns || []).includes(
                        col,
                    );
                    return (
                        <div key={col} className="flex items-center space-x-4">
                            <div className="w-32">
                                <label className="block text-sm font-medium text-gray-700">
                                    {col}
                                    {required ? " *" : ""}
                                </label>
                            </div>
                            <div className="flex-1">
                                <select
                                    value={data.mappings[col] || ""}
                                    onChange={(e) =>
                                        handleMappingChange(col, e.target.value)
                                    }
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Select column...</option>
                                    {previewData.headers?.map(
                                        (header, index) => (
                                            <option key={index} value={index}>
                                                Column {index + 1}: {header}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                        </div>
                    );
                })}
            </div>

            {previewData.data?.length > 0 && (
                <div className="mt-6">
                    <h4 className="font-medium text-gray-900 mb-2">
                        Data Preview (First 5 rows):
                    </h4>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    {(() => {
                                        const mappedFields = [
                                            ...new Set([
                                                ...(config.requiredColumns ||
                                                    []),
                                                ...(config.optionalColumns ||
                                                    []),
                                            ]),
                                        ];
                                        const mappedIndices = mappedFields.map(
                                            (field) => ({
                                                field,
                                                index:
                                                    data.mappings[field] !==
                                                    undefined
                                                        ? Number(
                                                              data.mappings[
                                                                  field
                                                              ],
                                                          )
                                                        : null,
                                            }),
                                        );

                                        if (
                                            mappedIndices.some(
                                                (m) => m.index !== null,
                                            )
                                        ) {
                                            return mappedIndices.map((m) => (
                                                <th
                                                    key={m.field}
                                                    className="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase"
                                                >
                                                    {m.field}
                                                    {(
                                                        config.requiredColumns ||
                                                        []
                                                    ).includes(m.field)
                                                        ? " *"
                                                        : ""}
                                                </th>
                                            ));
                                        }

                                        return (previewData.headers || []).map(
                                            (header, index) => (
                                                <th
                                                    key={index}
                                                    className="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase"
                                                >
                                                    {header}
                                                </th>
                                            ),
                                        );
                                    })()}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {previewData.data.map((row, rowIndex) => (
                                    <tr key={rowIndex}>
                                        {(() => {
                                            const mappedFields = [
                                                ...new Set([
                                                    ...(config.requiredColumns ||
                                                        []),
                                                    ...(config.optionalColumns ||
                                                        []),
                                                ]),
                                            ];
                                            const mappedIndices =
                                                mappedFields.map((field) => ({
                                                    field,
                                                    index:
                                                        data.mappings[field] !==
                                                        undefined
                                                            ? Number(
                                                                  data.mappings[
                                                                      field
                                                                  ],
                                                              )
                                                            : null,
                                                }));

                                            if (
                                                mappedIndices.some(
                                                    (m) => m.index !== null,
                                                )
                                            ) {
                                                return mappedIndices.map(
                                                    (m, cellIndex) => {
                                                        let cellValue =
                                                            m.index !== null
                                                                ? row[
                                                                      m.index
                                                                  ] ?? ""
                                                                : "";

                                                        if (
                                                            [
                                                                "start_time",
                                                                "end_time",
                                                            ].includes(m.field)
                                                        ) {
                                                            cellValue = toTimeString(
                                                                cellValue,
                                                            );
                                                        }

                                                        return (
                                                            <td
                                                                key={cellIndex}
                                                                className="px-2 py-1 text-gray-900"
                                                            >
                                                                {cellValue}
                                                            </td>
                                                        );
                                                    },
                                                );
                                            }

                                            return (row || []).map(
                                                (cell, cellIndex) => (
                                                    <td
                                                        key={cellIndex}
                                                        className="px-2 py-1 text-gray-900"
                                                    >
                                                        {cell}
                                                    </td>
                                                ),
                                            );
                                        })()}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex items-center justify-center min-h-screen px-4">
                <div
                    className="fixed inset-0 bg-gray-500 bg-opacity-75"
                    onClick={handleClose}
                ></div>

                <div className="relative bg-white rounded-lg max-w-4xl w-full p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-xl font-semibold text-gray-900">
                            Import {config.title}
                        </h2>
                        <button
                            onClick={handleClose}
                            className="text-gray-400 hover:text-gray-500"
                        >
                            <svg
                                className="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={handleSubmit}>
                        {step === 1 && renderFileSelection()}
                        {step === 2 && renderPreview()}

                        <div className="flex justify-between mt-6">
                            <button
                                type="button"
                                onClick={
                                    step === 2 ? () => setStep(1) : handleClose
                                }
                                className="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            >
                                {step === 2 ? "Back" : "Cancel"}
                            </button>

                            {step === 1 && data.file && (
                                <button
                                    type="button"
                                    onClick={() => setStep(2)}
                                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                                >
                                    Next
                                </button>
                            )}

                            {step === 2 && (
                                <button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (config.requiredColumns || []).some(
                                            (col) => !data.mappings[col],
                                        )
                                    }
                                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400"
                                >
                                    {processing
                                        ? "Importing..."
                                        : "Import Data"}
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
