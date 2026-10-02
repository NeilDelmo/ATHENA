export function projectStatusManager(initialStatus = 'ongoing', initiallyOpen = false) {
    return {
        statusManagerOpen: initiallyOpen,
        selectedStatus: initialStatus,
        submitting: false,

        async submitStatus(event) {
            const form = event.currentTarget;
            if (this.submitting || !form.reportValidity()) return;
            this.submitting = true;
            const confirmation = form.querySelector('[name="completion_confirmed"]');
            confirmation.value = '0';

            try {
                if (this.selectedStatus === 'completed') {
                    const result = await window.Swal.fire({
                        title: 'Mark project as completed?',
                        text: 'Completion permanently closes project monitoring. The status cannot be changed back, and Monitoring Tools, Progress Reports, and Terminal Reports become read-only. Conference and publication tracking remains available.',
                        icon: 'warning',
                        iconColor: '#b91c1c',
                        confirmButtonColor: '#b91c1c',
                        confirmButtonText: 'Mark completed',
                        cancelButtonText: 'Keep project open',
                        showCancelButton: true,
                        reverseButtons: true,
                        focusCancel: true,
                    });
                    if (!result.isConfirmed) {
                        this.submitting = false;
                        return;
                    }
                    confirmation.value = '1';
                }

                HTMLFormElement.prototype.submit.call(form);
            } catch (error) {
                this.submitting = false;
                throw error;
            }
        },
    };
}
