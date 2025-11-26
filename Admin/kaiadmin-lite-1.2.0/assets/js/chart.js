// Hàm định dạng tiền tệ VNĐ
function formatCurrency(value) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumSignificantDigits: 3
    }).format(value);
}

// Hàm định dạng ngày tháng
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.getDate() + '/' + (date.getMonth() + 1);
}

// Hàm vẽ biểu đồ doanh thu theo tháng
function drawMonthlyRevenueChart(data) {
    const ctx = document.getElementById('monthlyRevenueChart').getContext('2d');
    const labels = data.map(item => formatDate(item.date));
    const values = data.map(item => item.total);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu',
                data: values,
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.2)',
                borderWidth: 2,
                pointBackgroundColor: '#4CAF50',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: 12,
                            family: "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif"
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return 'Doanh thu: ' + formatCurrency(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return formatCurrency(value);
                        }
                    },
                    grid: {
                        drawBorder: false,
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Hàm vẽ biểu đồ doanh thu theo ngày
function drawDailyRevenueChart(data) {
    const ctx = document.getElementById('dailyRevenueChart').getContext('2d');
    const labels = data.map(item => item.date);
    const values = data.map(item => item.total);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu',
                data: values,
                backgroundColor: '#2196F3',
                borderColor: '#2196F3',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return 'Doanh thu: ' + formatCurrency(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}

// Hàm vẽ biểu đồ đơn hàng theo ngày
function drawDailyOrdersChart(data) {
    const ctx = document.getElementById('dailyOrdersChart').getContext('2d');
    const labels = data.map(item => item.date);
    const values = data.map(item => item.total);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Số đơn hàng',
                data: values,
                borderColor: '#FF9800',
                backgroundColor: 'rgba(255, 152, 0, 0.2)',
                borderWidth: 2,
                pointBackgroundColor: '#FF9800',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Hàm khởi tạo tất cả biểu đồ
function initCharts() {
    fetch('data.php')
        .then(response => response.json())
        .then(data => {
            drawMonthlyRevenueChart(data.monthlyData);
            drawDailyRevenueChart(data.dailyData);
            drawDailyOrdersChart(data.dailyOrdersData);
        })
        .catch(error => console.error('Error loading chart data:', error));
}

// Khởi tạo biểu đồ khi trang đã tải xong
document.addEventListener('DOMContentLoaded', initCharts); 