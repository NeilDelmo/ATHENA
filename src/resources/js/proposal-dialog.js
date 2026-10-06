export function createProposalDialog(loadDialog = () => import('sweetalert2')) {
    let dialogPromise;

    return {
        async fire(...args) {
            dialogPromise ??= loadDialog().catch((error) => {
                dialogPromise = null;
                throw error;
            });

            const { default: dialog } = await dialogPromise;
            return dialog.fire(...args);
        },
    };
}

export default createProposalDialog();
