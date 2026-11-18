const dialog = document.querySelector('#practice-dialog');
const questionField = document.querySelector('#question-id');
const questionTitle = document.querySelector('#dialog-question');
const answer = document.querySelector('#answer');
const wordCount = document.querySelector('#word-count');

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

document.querySelector('.dialog-close')?.addEventListener('click', () => dialog.close());
dialog?.addEventListener('click', (event) => {
  if (event.target === dialog) dialog.close();
});
answer?.addEventListener('input', () => {
  const count = answer.value.trim().split(/\s+/u).filter(Boolean).length;
  wordCount.textContent = `${count} ${count === 1 ? 'word' : 'words'}`;
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
