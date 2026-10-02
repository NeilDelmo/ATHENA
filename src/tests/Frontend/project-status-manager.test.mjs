import assert from 'node:assert/strict';
import test from 'node:test';
import { projectStatusManager } from '../../resources/js/project-status-manager.js';

function fixture(t, confirmed = true) {
    const originalWindow = globalThis.window;
    const originalForm = globalThis.HTMLFormElement;
    t.after(() => { globalThis.window = originalWindow; globalThis.HTMLFormElement = originalForm; });
    const dialogs = [];
    const submissions = [];
    globalThis.window = { Swal: { fire: async (options) => { dialogs.push(options); return { isConfirmed: confirmed }; } } };
    globalThis.HTMLFormElement = class {};
    globalThis.HTMLFormElement.prototype.submit = function () { submissions.push(this.confirmation.value); };
    const form = { confirmation: { value: '0' }, reportValidity: () => true, querySelector() { return this.confirmation; } };
    return { form, dialogs, submissions, event: { currentTarget: form } };
}

test('completion warns about final status and read-only reports before sending confirmation', async (t) => {
    const { event, dialogs, submissions } = fixture(t);
    const state = projectStatusManager('completed');
    await state.submitStatus(event);
    assert.equal(dialogs.length, 1);
    assert.match(dialogs[0].text, /status cannot be changed back/);
    assert.match(dialogs[0].text, /read-only/);
    assert.match(dialogs[0].text, /Conference and publication tracking remains available/);
    assert.equal(dialogs[0].focusCancel, true);
    assert.equal(dialogs[0].confirmButtonText, 'Mark completed');
    assert.deepEqual(submissions, ['1']);
    await state.submitStatus(event);
    assert.equal(submissions.length, 1);
});

test('cancelling completion leaves the project open and does not submit', async (t) => {
    const { event, form, submissions } = fixture(t, false);
    const state = projectStatusManager('completed');
    await state.submitStatus(event);
    assert.deepEqual(submissions, []);
    assert.equal(form.confirmation.value, '0');
    assert.equal(state.submitting, false);
});

test('ongoing and delayed changes do not require completion acknowledgement', async (t) => {
    const { event, dialogs, submissions } = fixture(t);
    await projectStatusManager('delayed').submitStatus(event);
    assert.equal(dialogs.length, 0);
    assert.deepEqual(submissions, ['0']);
});

test('an invalid status form cannot open confirmation or submit', async (t) => {
    const { event, form, dialogs, submissions } = fixture(t);
    form.reportValidity = () => false;
    await projectStatusManager('completed').submitStatus(event);
    assert.equal(dialogs.length, 0);
    assert.equal(submissions.length, 0);
});
