export function studentDisplayName(student) {
    if (!student) return 'Student';

    const name = String(student.name ?? '').trim();
    const admissionNumber = String(student.admission_number ?? '').trim();

    if (name && admissionNumber) {
        return `${name} (${admissionNumber})`;
    }

    return name || admissionNumber || 'Student';
}

