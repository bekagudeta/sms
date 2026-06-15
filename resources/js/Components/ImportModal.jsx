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
    const [error, setError] = useState("");
    const [loadingPreview, setLoadingPreview] = useState(false);

    const MAX_FILE_MB = 10;

    const formatBytes = (bytes) => {
        if (!bytes && bytes !== 0) return "";
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    };

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
            // NOTE: do NOT alias department_id to department_code/name — a code
            // value (e.g. "SE") landing in department_id breaks integer validation.
            // department_code / department_name map to their own fields instead.
            department_id: [
                "department_id",
                "dept_id",
            ],
            hours_per_week: [
                "hours_per_week",
                "hours per week",
                "hours",
                "weekly_hours",
                "prerequisites",
            ],
            teacher_code: ["teacher_code", "teacher_id", "teacher_ids"],
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

        setError("");

        const validTypes = [".csv", ".xlsx", ".xls"];
        const fileExtension = "." + file.name.split(".").pop().toLowerCase();

        if (!validTypes.includes(fileExtension)) {
            setError("Unsupported file type. Please upload a .csv, .xlsx, or .xls file.");
            return;
        }

        if (file.size > MAX_FILE_MB * 1024 * 1024) {
            setError(`File is too large (${formatBytes(file.size)}). Maximum allowed size is ${MAX_FILE_MB} MB.`);
            return;
        }

        setData("file", file);
        setLoadingPreview(true);

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

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.content : "";
        if (csrfToken) {
            formData.append("_token", csrfToken);
        }
        try {
            const response = await fetch(route("import.preview"), {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken,
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
                credentials: "include",
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

            setPreviewData({
                headers: result.headers || [],
                data: result.rows || [],
            });
            setData("mappings", finalMappings);
            setStep(2);
        } catch (err) {
            console.error("Error reading file:", err);
            setError("We couldn't read that file. Please check it's a valid CSV/Excel file and try again.");
            setData("file", null);
            if (fileInputRef.current) fileInputRef.current.value = "";
        } finally {
            setLoadingPreview(false);
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

        setError("");
        post("/import/bulk", {
            data: formData,
            onSuccess: () => {
                onSuccess();
                handleClose();
            },
            onError: (errs) => {
                console.error("Import errors:", errs);
                const firstError = errs && typeof errs === "object" ? Object.values(errs)[0] : null;
                setError(firstError || "Import failed. Please check the file and column mappings, then try again.");
            },
        });
    };

    const handleClose = () => {
        setStep(1);
        setPreviewData([]);
        setData("file", null);
        setData("mappings", {});
        setData("skip_header", true);
        setError("");
        setLoadingPreview(false);
        if (fileInputRef.current) fileInputRef.current.value = "";
        reset();
        onClose();
    };

    const renderFileSelection = () => (
        <div>
            <div
                role="button"
                tabIndex={0}
                onClick={() => !loadingPreview && fileInputRef.current?.click()}
                onKeyDown={(e) => {
                    if ((e.key === "Enter" || e.key === " ") && !loadingPreview) {
                        e.preventDefault();
                        fileInputRef.current?.click();
                    }
                }}
                className={`relative cursor-pointer rounded-xl border-2 border-dashed p-8 text-center transition ${
                    dragActive
                        ? "border-primary bg-primary/5"
                        : "border-primary/25 hover:border-primary/50 hover:bg-gray-50"
                } ${loadingPreview ? "pointer-events-none opacity-70" : ""}`}
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

                {loadingPreview ? (
                    <div className="space-y-3">
                        <svg className="mx-auto h-10 w-10 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <p className="text-sm font-medium text-primary">Reading file…</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="mx-auto h-12 w-12 text-primary/60">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path
                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </div>

                        <div>
                            <p className="text-lg font-medium text-primary">
                                Drop your file here, or <span className="text-primary underline">browse</span>
                            </p>
                            <p className="mt-1 text-sm text-gray-500">
                                Supports CSV and Excel (.csv, .xlsx, .xls) · up to {MAX_FILE_MB} MB
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 className="font-medium text-gray-900 mb-2">
                    Required Columns:
                </h4>
                <div className="text-sm text-gray-600">
                    {config.requiredColumns.map((col) => (
                        <span
                            key={col}
                            className="inline-block px-2 py-1 bg-primary/10 text-primary rounded mr-2 mb-2"
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
                        className="h-4 w-4 rounded border-primary/30 text-primary focus:ring-primary"
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
                                    className="app-input block w-full"
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
            <div className="flex min-h-screen items-center justify-center px-4 py-6">
                <div className="fixed inset-0 bg-black/40" onClick={handleClose}></div>

                <div className="relative max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                    <div className="mb-6 flex items-center justify-between">
                        <h2 className="text-xl font-semibold text-primary">
                            Import {config.title}
                        </h2>
                        <button
                            onClick={handleClose}
                            className="text-gray-400 transition hover:text-gray-600"
                            aria-label="Close"
                        >
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {error && (
                        <div className="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <svg className="mt-0.5 h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            <span>{error}</span>
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        {step === 1 && renderFileSelection()}
                        {step === 2 && renderPreview()}

                        <div className="mt-6 flex justify-between">
                            <button
                                type="button"
                                onClick={step === 2 ? () => setStep(1) : handleClose}
                                className="app-secondary-btn"
                            >
                                {step === 2 ? "Back" : "Cancel"}
                            </button>

                            {step === 2 && (
                                <button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (config.requiredColumns || []).some(
                                            (col) => !data.mappings[col],
                                        )
                                    }
                                    className="app-primary-btn"
                                >
                                    {processing ? "Importing…" : "Import Data"}
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
