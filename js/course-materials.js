document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('material-search');
    const count = document.getElementById('material-count');
    const emptyMessage = document.getElementById('materials-empty');
    const cards = Array.from(document.querySelectorAll('.subject-material-card'));

    if (!search || !count || !emptyMessage) return;

    const filterMaterials = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        cards.forEach((card) => {
            const searchableText = `${card.dataset.search ?? ''} ${card.innerText}`.toLocaleLowerCase();
            const matches = searchableText.includes(query);
            card.hidden = !matches;
            if (matches) visibleCount += 1;
        });

        count.textContent = `${visibleCount} subject${visibleCount === 1 ? '' : 's'}`;
        emptyMessage.hidden = visibleCount > 0;
    };

    search.addEventListener('input', filterMaterials);
    filterMaterials();
});
