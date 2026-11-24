const dialog = document.querySelector('#practice-dialog');
const questionField = document.querySelector('#question-id');
const questionTitle = document.querySelector('#dialog-question');
const answer = document.querySelector('#answer');
const wordCount = document.querySelector('#word-count');
const answerForm = document.querySelector('#answer-form');
const submitButton = document.querySelector('#submit-answer');
const submissionError = document.querySelector('#submission-error');
const submissionProgress = document.querySelector('#submission-progress');
const submissionStatus = document.querySelector('#submission-status');
const submissionDetail = document.querySelector('#submission-detail');
const progressFill = document.querySelector('#progress-fill');
let isSubmitting = false;

if (document.querySelector('[data-results-page]')) {
  window.scrollTo(0, 0);
  window.addEventListener('pageshow', () => window.scrollTo(0, 0), { once: true });
}

const previousQuestionId = questionField?.value;
if (previousQuestionId && dialog) {
  const previousQuestion = document.querySelector(`.start-button[data-question="${previousQuestionId}"]`);
  if (previousQuestion) {
    questionTitle.textContent = previousQuestion.dataset.title;
    answer.dispatchEvent(new Event('input'));
    dialog.showModal();
  }
}

document.querySelectorAll('.start-button').forEach((button) => {
  button.addEventListener('click', () => {
    questionField.value = button.dataset.question;
    questionTitle.textContent = button.dataset.title;
    answer.value = '';
    wordCount.textContent = '0 words';
    dialog.showModal();
    answer.focus();
  });
});

document.querySelector('.dialog-close')?.addEventListener('click', () => {
  if (!isSubmitting) dialog.close();
});
dialog?.addEventListener('click', (event) => {
  if (event.target === dialog && !isSubmitting) dialog.close();
});
dialog?.addEventListener('cancel', (event) => {
  if (isSubmitting) event.preventDefault();
});
answer?.addEventListener('input', () => {
  const count = answer.value.trim().split(/\s+/u).filter(Boolean).length;
  wordCount.textContent = `${count} ${count === 1 ? 'word' : 'words'}`;
});

answerForm?.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (isSubmitting) return;

  isSubmitting = true;
  submissionError.hidden = true;
  submissionError.textContent = '';
  submissionProgress.hidden = false;
  submissionProgress.classList.remove('is-complete');
  submissionStatus.textContent = 'Sending your answer…';
  submissionDetail.textContent = 'Your feedback will open automatically when it is saved.';
  submitButton.disabled = true;
  submitButton.setAttribute('aria-busy', 'true');
  answerForm.setAttribute('aria-busy', 'true');

  const progressTimers = [
    window.setTimeout(() => {
      submissionStatus.textContent = 'Gemini is reviewing your answer…';
    }, 1800),
    window.setTimeout(() => {
      submissionDetail.textContent = 'This can take a little while. Keep this window open; no refresh is needed.';
    }, 12000),
  ];

  try {
    const response = await fetch(answerForm.action, {
      method: 'POST',
      body: new FormData(answerForm),
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
      throw new Error(payload.message ?? validationMessage ?? 'We could not finish this interview. Please try again.');
    }

    if (typeof payload.redirect_url !== 'string') {
      throw new Error('Your response was received, but we could not open the saved feedback. Please check your history.');
    }

    progressFill?.style.setProperty('animation', 'none');
    submissionProgress.classList.add('is-complete');
    submissionStatus.textContent = 'Feedback saved';
    submissionDetail.textContent = 'Opening your results…';
    window.location.assign(payload.redirect_url);
  } catch (error) {
    submissionProgress.hidden = true;
    submissionError.textContent = error instanceof Error
      ? error.message
      : 'We could not reach the server. Check your connection and try again.';
    submissionError.hidden = false;
    submissionError.focus();
  } finally {
    progressTimers.forEach(window.clearTimeout);
    isSubmitting = false;
    submitButton.disabled = false;
    submitButton.removeAttribute('aria-busy');
    answerForm.removeAttribute('aria-busy');
  }
});

document.querySelectorAll('.filter-chip').forEach((chip) => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('.filter-chip').forEach((item) => item.classList.remove('selected'));
    chip.classList.add('selected');
    const selected = chip.dataset.filter;
    document.querySelectorAll('.question-card').forEach((card) => {
      const category = card.dataset.category;
      const visible = selected === 'all'
        || (selected === 'Behavioral' && category === 'Behavioral')
        || (selected !== 'Behavioral' && category === selected);
      card.hidden = !visible;
    });
  });
});
