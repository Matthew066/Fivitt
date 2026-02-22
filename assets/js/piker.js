/*------------------------------------- Age Picker -------------------------------------*/
document.addEventListener('DOMContentLoaded', function() {
    const picker = document.querySelector('.age-picker');

    // Create age items dynamically from 1 to 100
    for (let i = 0; i <= 50; i++) {
    	const ageItem = document.createElement('div');
    	ageItem.classList.add('age-item');
    	const ageSpan = document.createElement('span');
        ageSpan.textContent = i === 0 ? 24 : i; // Add sentinel item at the start
        ageItem.appendChild(ageSpan);
        picker.appendChild(ageItem);
    }
    const sentinel = document.createElement('div'); // Add sentinel item at the end
    sentinel.classList.add('age-item');
    const sentinelSpan = document.createElement('span');
    sentinelSpan.textContent = 1;
    sentinel.appendChild(sentinelSpan);
    picker.appendChild(sentinel);

    // Select default number (e.g., 28)
    const defaultNumber = 28; // Change this to set your desired default number
    const items = document.querySelectorAll('.age-item');
    items.forEach(item => {
    	if (item.textContent.trim() === String(defaultNumber)) {
    		item.classList.add('selected1');
    		item.appendChild(document.createTextNode(' years'));
    	}
    });

    // Scroll event listener for picker
    picker.addEventListener('scroll', function() {
    	const center = picker.scrollTop + picker.offsetHeight / 2;
    	items.forEach(item => {
    		const itemCenter = item.offsetTop + item.offsetHeight / 2;
    		const distance = Math.abs(center - itemCenter);
    		if (distance < item.offsetHeight / 2) {
    			item.classList.add('selected1');
    			if (!item.textContent.includes('years')) {
    				item.appendChild(document.createTextNode(' years'));
    			}
    		} else {
    			item.classList.remove('selected1');
    			if (item.textContent.includes('years')) {
    				item.textContent = item.textContent.replace(' years', '');
    			}
    		}
    	});

        // Handle circular scroll
        if (picker.scrollTop === 0) {
        	picker.scrollTop = picker.scrollHeight - picker.offsetHeight * 2;
        } else if (picker.scrollTop >= picker.scrollHeight - picker.offsetHeight) {
        	picker.scrollTop = picker.offsetHeight;
        }
    });

    // Initial scroll to the default number
    const defaultItem = Array.from(items).find(item => item.textContent.trim() === String(defaultNumber));
    if (defaultItem) {
    	picker.scrollTop = defaultItem.offsetTop - picker.offsetHeight / 2 + defaultItem.offsetHeight / 2;
    }
});

/*------------------------------------- Height Picker -------------------------------------*/
document.addEventListener('DOMContentLoaded', function() {
    const picker = document.querySelector('.height-picker');

    // Create height items dynamically from 160 to 200
    for (let i = 160; i <= 200; i++) {
        const heightItem = document.createElement('div');
        heightItem.classList.add('height-item');
        const heightSpan = document.createElement('span');
        heightSpan.textContent = i; // Add height value
        heightItem.appendChild(heightSpan);
        picker.appendChild(heightItem);
    }

    // Select default height (e.g., 170)
    const defaultHeight = 170; // Change this to set your desired default height
    const items = document.querySelectorAll('.height-item');
    items.forEach(item => {
        if (item.textContent.trim() === String(defaultHeight)) {
            item.classList.add('selected1');
            item.appendChild(document.createTextNode(' cm'));
        }
    });

    // Scroll event listener for picker
    picker.addEventListener('scroll', function() {
        const center = picker.scrollTop + picker.offsetHeight / 2;
        items.forEach(item => {
            const itemCenter = item.offsetTop + item.offsetHeight / 2;
            const distance = Math.abs(center - itemCenter);
            if (distance < item.offsetHeight / 2) {
                item.classList.add('selected1');
                if (!item.textContent.includes('cm')) {
                    item.appendChild(document.createTextNode(' cm'));
                }
            } else {
                item.classList.remove('selected1');
                if (item.textContent.includes('cm')) {
                    item.textContent = item.textContent.replace(' cm', '');
                }
            }
        });

        // Handle circular scroll
        if (picker.scrollTop === 0) {
            picker.scrollTop = picker.scrollHeight - picker.offsetHeight * 2;
        } else if (picker.scrollTop >= picker.scrollHeight - picker.offsetHeight) {
            picker.scrollTop = picker.offsetHeight;
        }
    });

    // Initial scroll to the default height
    const defaultItem = Array.from(items).find(item => item.textContent.trim() === String(defaultHeight));
    if (defaultItem) {
        picker.scrollTop = defaultItem.offsetTop - picker.offsetHeight / 2 + defaultItem.offsetHeight / 2;
    }
});


/*------------------------------------- Weight Picker -------------------------------------*/
document.addEventListener('DOMContentLoaded', function() {
    const picker = document.querySelector('.weight-picker');

    // Create weight items dynamically from 30 to 150 kg
    for (let i = 30; i <= 150; i++) {
        const weightItem = document.createElement('div');
        weightItem.classList.add('weight-item');
        const weightSpan = document.createElement('span');
        weightSpan.textContent = i; // Add weight value
        weightItem.appendChild(weightSpan);
        picker.appendChild(weightItem);
    }

    // Select default weight (e.g., 70 kg)
    const defaultWeight = 70; // Change this to set your desired default weight
    const items = document.querySelectorAll('.weight-item');
    items.forEach(item => {
        if (item.textContent.trim() === String(defaultWeight)) {
            item.classList.add('selected1');
            item.appendChild(document.createTextNode(' kg'));
        }
    });

    // Scroll event listener for picker
    picker.addEventListener('scroll', function() {
        const center = picker.scrollTop + picker.offsetHeight / 2;
        items.forEach(item => {
            const itemCenter = item.offsetTop + item.offsetHeight / 2;
            const distance = Math.abs(center - itemCenter);
            if (distance < item.offsetHeight / 2) {
                item.classList.add('selected1');
                if (!item.textContent.includes('kg')) {
                    item.appendChild(document.createTextNode(' kg'));
                }
            } else {
                item.classList.remove('selected1');
                if (item.textContent.includes('kg')) {
                    item.textContent = item.textContent.replace(' kg', '');
                }
            }
        });

        // Handle circular scroll
        if (picker.scrollTop === 0) {
            picker.scrollTop = picker.scrollHeight - picker.offsetHeight * 2;
        } else if (picker.scrollTop >= picker.scrollHeight - picker.offsetHeight) {
            picker.scrollTop = picker.offsetHeight;
        }
    });

    // Initial scroll to the default weight
    const defaultItem = Array.from(items).find(item => item.textContent.trim() === String(defaultWeight));
    if (defaultItem) {
        picker.scrollTop = defaultItem.offsetTop - picker.offsetHeight / 2 + defaultItem.offsetHeight / 2;
    }
});

/*------------------------------------- Day Picker -------------------------------------*/
document.addEventListener('DOMContentLoaded', function() {
    const picker = document.querySelector('.day-picker');

    // Create day items dynamically from 1 to 365
    for (let i = 0; i <= 50; i++) {
        const dayItem = document.createElement('div');
        dayItem.classList.add('day-item');
        const daySpan = document.createElement('span');
        daySpan.textContent = i === 0 ? 50 : i; // Add sentinel item at the start
        dayItem.appendChild(daySpan);
        picker.appendChild(dayItem);
    }
    const sentinel = document.createElement('div'); // Add sentinel item at the end
    sentinel.classList.add('day-item');
    const sentinelSpan = document.createElement('span');
    sentinelSpan.textContent = 1;
    sentinel.appendChild(sentinelSpan);
    picker.appendChild(sentinel);

    // Select default number (e.g., 4)
    const defaultNumber = 4; // Change this to set your desired default number
    const items = document.querySelectorAll('.day-item');
    items.forEach(item => {
        if (item.textContent.trim() === String(defaultNumber)) {
            item.classList.add('selected1');
            item.appendChild(document.createTextNode(' days'));
        }
    });

    // Scroll event listener for picker
    picker.addEventListener('scroll', function() {
        const center = picker.scrollTop + picker.offsetHeight / 2;
        items.forEach(item => {
            const itemCenter = item.offsetTop + item.offsetHeight / 2;
            const distance = Math.abs(center - itemCenter);
            if (distance < item.offsetHeight / 2) {
                item.classList.add('selected1');
                if (!item.textContent.includes('days')) {
                    item.appendChild(document.createTextNode(' days'));
                }
            } else {
                item.classList.remove('selected1');
                if (item.textContent.includes('days')) {
                    item.textContent = item.textContent.replace(' days', '');
                }
            }
        });

        // Handle circular scroll
        if (picker.scrollTop === 0) {
            picker.scrollTop = picker.scrollHeight - picker.offsetHeight * 2;
        } else if (picker.scrollTop >= picker.scrollHeight - picker.offsetHeight) {
            picker.scrollTop = picker.offsetHeight;
        }
    });

    // Initial scroll to the default number
    const defaultItem = Array.from(items).find(item => item.textContent.trim() === String(defaultNumber));
    if (defaultItem) {
        picker.scrollTop = defaultItem.offsetTop - picker.offsetHeight / 2 + defaultItem.offsetHeight / 2;
    }
});