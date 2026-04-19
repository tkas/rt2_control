let allPlans = [];

document.addEventListener('DOMContentLoaded', async () => {
    const tableBody = document.getElementById('history-table-body');
    const filterBtns = document.querySelectorAll('.filter-btn');

    // 1. Fetch data from backend
    try {
        const response = await fetch('get_all_plans.php');
        const result = await response.json();

        if (!result.success) throw new Error(result.error);
        
        allPlans = result.data;
        renderTable('all'); // Render all by default

    } catch (error) {
        tableBody.innerHTML = `<tr><td colspan="6" style="color: red; text-align:center;">Error loading data: ${error.message}</td></tr>`;
    }

    // 2. Set up click events for filter buttons
    filterBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Visually update which button is active
            filterBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');

            // Re-render table with the chosen filter
            renderTable(e.target.getAttribute('data-filter'));
        });
    });
});

function renderTable(filter) {
    const tableBody = document.getElementById('history-table-body');
    tableBody.innerHTML = '';

    // Get current time in UNIX seconds to compare against database timestamps
    const nowUnix = Math.floor(Date.now() / 1000);
    
    // Filter the array based on the selected button
    const filteredPlans = allPlans.filter(plan => {
        if (filter === 'future') return plan.rec_start_time > nowUnix;
        if (filter === 'past') return plan.rec_start_time <= nowUnix;
        return true; // 'all'
    });

    if (filteredPlans.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 15px;">No recordings found for this filter.</td></tr>';
        return;
    }

    // Format options to match the format used in main_page.php
    const localeOptions = { 
        year: 'numeric', month: 'numeric', day: 'numeric', 
        hour: '2-digit', minute: '2-digit' 
    };

    // Build the table rows
    filteredPlans.forEach(plan => {
        const tr = document.createElement('tr');
        tr.style.borderTop = '1px solid #ddd';

        const isFuture = plan.rec_start_time > nowUnix;
        const isPast = plan.end_time < nowUnix;

        let statusHtml;

        if (isFuture)
        {
            statusHtml = '<span style="color: #ffc43d; font-weight: bold;">Upcoming</span>';
        }
        else if (isPast)
        {
            statusHtml = '<span style="color: gray;">Completed</span>';
        }
        else // recording
        {
            statusHtml = '<span style="color: #06d6a0;">Currently Recording</span>';
        }

        // Populate table cells, converting UNIX timestamps back to local browser time
        tr.innerHTML = `
            <td style="padding: 8px;"><strong>${plan.object_name}</strong></td>
            <td style="padding: 8px;">${plan.id}</td>
            <td style="padding: 8px;">${new Date(plan.obs_start_time * 1000).toLocaleString("cs-CZ", localeOptions)}</td>
            <td style="padding: 8px;">${new Date(plan.rec_start_time * 1000).toLocaleString("cs-CZ", localeOptions)}</td>
            <td style="padding: 8px;">${new Date(plan.end_time * 1000).toLocaleString("cs-CZ", localeOptions)}</td>
            <td style="padding: 8px;">${statusHtml}</td>
        `;

        tableBody.appendChild(tr);
    });
}