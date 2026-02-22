/*------------------------------------- Weight Chart -------------------------------------*/
$(document).ready(function() {
	var ctx = $('#weightChart');
	var weightChart = new Chart(ctx, {
		type: 'line',
		data: {
			labels: ['NOV', 'DEC', 'JAN', 'FEB', 'MAR', 'APR'],
			datasets: [{
				label: 'Weight',
				data: [85,76,80,74,85,80,86,75,85,78],
				borderColor: '#7FD83B',
				borderWidth: 2,
				backgroundColor: 'transparent',
				pointBackgroundColor: '#7FD83B',
				pointHoverRadius: 7
			}]
		},
		options: {
			scales: {
				yAxes: [{
					ticks: {
						beginAtZero: false,
						suggestedMin: 60,
						suggestedMax: 90
					},
					gridLines: {
                        color: '#F5F5F5', // Grid lines color
                        lineWidth: 2, // Grid lines width
                        borderDash: [5, 5] // Dashes and gaps
                    }
                }],
                xAxes: [{
                	gridLines: {
                        display: false // Hide vertical grid lines
                        
                    }
                }]
            },
            tooltips: {
            	callbacks: {
            		label: function(tooltipItem, data) {
            			return tooltipItem.yLabel + ' kg';
            		}
            	}
            },
            legend: {
            	display: false
            },
            elements: {
            	line: {
                    tension: 0.3 // Smoothing effect
                }
            }
        }
    });

    // Tooltip customization
    Chart.defaults.global.tooltips.custom = function(tooltip) {
    	if (!tooltip) return;
    	tooltip.displayColors = false;
    	tooltip.bodyFontSize = 14;
    	tooltip.backgroundColor = 'rgba(0,0,0,0.7)';
    	tooltip.xPadding = 10;
    	tooltip.yPadding = 10;
    };
});

