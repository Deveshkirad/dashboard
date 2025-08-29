document.addEventListener('DOMContentLoaded', function() {
    // Only run this script on the timetable page by checking for a required element/variable.
    if (typeof selectedBatchId === 'undefined' || !document.getElementById('timetableModal')) {
        return;
    }

    const timetableModal = new bootstrap.Modal(document.getElementById('timetableModal'));
    const form = document.getElementById('timetableSlotForm');
    const subjectDropdown = form.elements.subject_id;
    const teacherDropdown = form.elements.teacher_id;
    const saveButton = document.getElementById('saveTimetableChanges');
    const clearButton = document.getElementById('clearSlotButton');
    let currentSlot = null;

    /**
     * Shows a toast notification.
     * @param {string} message The message to display.
     * @param {string} type 'success' or 'error'.
     */
    function showToast(message, type = 'success') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            console.warn('Toast container not found. Cannot display toast message.');
            alert(message); // Fallback to alert
            return;
        }
        const toastId = 'toast-' + Date.now();
        const toastBg = type === 'success' ? 'bg-success' : 'bg-danger';

        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white ${toastBg} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    /**
     * Fetches timetable data for the selected batch and populates the grid.
     */
    async function loadTimetableData() {
        if (selectedBatchId <= 0) {
            return; // Don't fetch if no batch is selected
        }

        try {
            const response = await fetch(`get_timetable_data.php?batch_id=${selectedBatchId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }
            const data = await response.json();

            if (data.status === 'success') {
                updateGrid(data.slots);
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching timetable data:', error);
            showToast('Failed to load timetable data.', 'error');
        }
    }

    /**
     * Updates the grid with the fetched slot data.
     * @param {Array} slotsData Array of slot objects from the server.
     */
    function updateGrid(slotsData) {
        // First, reset all slots to their default empty state
        document.querySelectorAll('.timetable-slot').forEach(slot => {
            slot.innerHTML = 'Click to add';
            slot.className = 'timetable-slot empty-slot'; // Reset classes
            delete slot.dataset.subjectId;
            delete slot.dataset.teacherId;
        });

        // Now, populate the grid with data
        slotsData.forEach(slotData => {
            const slotEl = document.querySelector(`.timetable-slot[data-day="${slotData.day_of_week}"][data-period="${slotData.period_number}"]`);
            if (slotEl) {
                slotEl.classList.remove('empty-slot');
                slotEl.innerHTML = `
                    <div class="slot-subject">${slotData.subject_name}</div>
                    <div class="slot-teacher">${slotData.teacher_name}</div>
                `;
                // Store data in the element for easy access when opening the modal
                slotEl.dataset.subjectId = slotData.subject_id;
                slotEl.dataset.teacherId = slotData.teacher_id;
            }
        });
    }

    /**
     * Filters the teacher dropdown based on the selected subject.
     * Auto-selects the teacher if only one is found for that subject.
     * @param {string} selectedSubjectId The ID of the selected subject.
     */
    function filterTeachersBySubject(selectedSubjectId) {
        teacherDropdown.innerHTML = ''; // Clear existing options

        if (!selectedSubjectId) {
            teacherDropdown.innerHTML = '<option value="">-- Select a Subject First --</option>';
            return;
        }

        // Find teachers assigned to the selected subject from the global variable
        const relevantTeachers = teachersWithSubjects.filter(
            teacher => teacher.subject_id == selectedSubjectId
        );

        if (relevantTeachers.length > 0) {
            // Add a placeholder only if there's more than one teacher for a subject
            if (relevantTeachers.length > 1) {
                teacherDropdown.innerHTML = '<option value="">-- Select Teacher --</option>';
            }

            relevantTeachers.forEach(teacher => {
                const option = document.createElement('option');
                option.value = teacher.id;
                option.textContent = teacher.name;
                teacherDropdown.appendChild(option);
            });
        } else {
            teacherDropdown.innerHTML = '<option value="">-- No Teacher Assigned --</option>';
        }
    }

    /**
     * Handles the click event on a timetable slot.
     * @param {Event} event The click event.
     */
    function handleSlotClick(event) {
        currentSlot = event.currentTarget;

        // Populate hidden fields
        form.elements.day_of_week.value = currentSlot.dataset.day;
        form.elements.period_number.value = currentSlot.dataset.period;

        // Set the subject from the clicked slot's data
        const subjectId = currentSlot.dataset.subjectId || '';
        subjectDropdown.value = subjectId;

        // Filter teachers based on the subject, then set the teacher value
        filterTeachersBySubject(subjectId);
        teacherDropdown.value = currentSlot.dataset.teacherId || '';

        // Show or hide the "Clear Slot" button
        clearButton.style.display = currentSlot.classList.contains('empty-slot') ? 'none' : 'block';

        timetableModal.show();
    }

    /**
     * Saves the changes made in the modal.
     */
    async function saveSlot() {
        const formData = new FormData(form);

        try {
            const response = await fetch('save_timetable_slot.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                showToast(data.message, 'success');
                await loadTimetableData(); // Reload data to show the change
                timetableModal.hide();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error saving slot:', error);
            showToast('An unexpected error occurred while saving.', 'error');
        }
    }

    /**
     * Clears the currently selected slot.
     */
    async function clearSlot() {
        const formData = new FormData();
        formData.append('batch_id', form.elements.batch_id.value);
        formData.append('day_of_week', form.elements.day_of_week.value);
        formData.append('period_number', form.elements.period_number.value);
        formData.append('clear', 'true'); // Add a flag to indicate clearing

        try {
            const response = await fetch('save_timetable_slot.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                showToast(data.message, 'success');
                await loadTimetableData(); // Reload data
                timetableModal.hide();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error clearing slot:', error);
            showToast('An unexpected error occurred while clearing the slot.', 'error');
        }
    }

    // --- Event Listeners ---
    // Attach a click listener to each slot to open the modal.
    document.querySelectorAll('.timetable-slot').forEach(slot => {
        slot.addEventListener('click', handleSlotClick);
    });

    // Add event listener to the subject dropdown to dynamically update teachers
    subjectDropdown.addEventListener('change', () => {
        filterTeachersBySubject(subjectDropdown.value);
    });

    // Attach listeners to the modal buttons.
    saveButton.addEventListener('click', saveSlot);
    clearButton.addEventListener('click', clearSlot);

    // Initial fetch of data
    loadTimetableData();
});
