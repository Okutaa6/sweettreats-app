/**
 * Theme: Metrica - Responsive Bootstrap 4 Admin Dashboard
 * Author: Mannatthemes
 * Dashboard Js
 */

//saldo
var options = {
  chart: {
      height: 325,
      type: 'donut',
      toolbar: {
        show: true,
        tools: {
          download: true,
          selection: true,
        }, 
      },
  }, 
  plotOptions: {
    pie: {
      donut: {
        size: '60%'
      }
    }
  },
  dataLabels: {
    enabled: false,
  },
 
  series: [5000000000, 2500000000, 1500000000, 500000000, 500000000],
  legend: {
      show: true,
      position: 'bottom',
      horizontalAlign: 'center',
      verticalAlign: 'middle',
      floating: false,
      fontSize: '14px',
      offsetX: 0,
      offsetY: -13
  },
  labels: ["Mandiri", "BNI", "BJB", "CIMB", "Muamalat"],
  //colors: ["#e0e7fd", "#4d79f6", "#4ac7ec"],
 
  responsive: [{
      breakpoint: 600,
      options: {
        plotOptions: {
            donut: {
              customScale: 0.2
            }
          },        
          chart: {
              height: 240
          },
          legend: {
              show: false
          },
      }
  }],

  tooltip: {
    y: {
        formatter: function (val) {
            return  parseFloat(val).toLocaleString('en')
        }
    }
  }
  
}

var chart = new ApexCharts(document.querySelector("#dashboard_saldo"), options);
chart.render();

//penerimaan vs pengeluaran
var options = {
  chart: {
    height: 380,
    type: 'line',
    zoom: {
      enabled: false
    },
    toolbar: {
      show: false
    }
  },
  colors: ['#1ecab8', '#f1646c'],
  dataLabels: {
    enabled: true,
  },
  stroke: {
    width: [3, 3],
    curve: 'smooth'
  },
  series: [{
    name: "Penerimaan",
    data: [2800000000, 1900000000, 1300000000, 3600000000, 3200000000, 3200000000]
  },
  {
    name: "Pengeluaran",
    data: [1200000000, 2300000000, 1400000000, 1800000000, 1700000000, 1300000000]
  }
  ],
  title: {
    text: 'Data 6 Bulan Terakhir',
    align: 'left'
  },
  grid: {
    row: {
      colors: ['transparent', 'transparent'],
      opacity: 0.2
    },
    borderColor: '#f1f3fa'
  },
  markers: {
    style: 'inverted',
    size: 6
  },
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    axisBorder: {
      show: true,
      color: '#bec7e0',
    },  
    axisTicks: {
      show: true,
      color: '#bec7e0',
    },    
    title: {
      text: 'Month'
    }
  },
  yaxis: {
    title: {
      text: '',
    },
  },
  legend: {
    position: 'top',
    horizontalAlign: 'right',
    floating: true,
    offsetY: -25,
    offsetX: -5
  },
  responsive: [{
    breakpoint: 600,
    options: {
      chart: {
        toolbar: {
          show: false
        }
      },
      legend: {
        show: false
      },
    }
  }]
}

var chart = new ApexCharts(
  document.querySelector("#apex_line"),
  options
);

chart.render();

//penerimaan vs pengeluaran
var options = {
    chart: {
        height: 325,
        type: 'bar',
        toolbar: {
          show: true,
          tools: {
            download: true,
            selection: true,
          }, 
        },
    },
    plotOptions: {
        bar: {
            horizontal: false,
            endingShape: 'rounded',
            columnWidth: '55%',
        },
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    colors: ['#1ecab8', '#f1646c'],
    series: [{
        name: 'Penerimaan',
        data: [9400000000, 5500000000, 5700000000, 6200000000, 6100000000, 5800000000]
    }, {
        name: 'Pengeluaran',
        data: [7600000000, 6500000000, 6000000000, 5800000000, 5000000000, 5200000000]
    }],
    xaxis: {
        categories: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'],
        axisBorder: {
            show: true,
            color: '#bec7e0',
          },  
          axisTicks: {
            show: true,
            color: '#bec7e0',
        },    
    },
    legend: {
        offsetY: -10,
    },
    yaxis: {
        title: {
            text: ''
        },
        labels: {
          show: false
        },
    },
    fill: {
        opacity: 1

    },
    grid: {
        row: {
            colors: ['transparent', 'transparent'], // takes an array which will be repeated on columns
            opacity: 0.2
        },
        borderColor: '#f1f3fa'
    },
    tooltip: {
        y: {
            formatter: function (val) {
                return  parseFloat(val).toLocaleString('en')
            }
        }
    }
}

var chart = new ApexCharts(
    document.querySelector("#apex_column"),
    options
);

chart.render();


//penampungan sumber dana
var options = {
  chart: {
      height: 350,
      type: 'bar',
      toolbar: {
        show: true,
        tools: {
          download: true,
          selection: true,
        }, 
      },
  },
  plotOptions: {
      bar: {
          horizontal: true,
      }
  },
  dataLabels: {
      enabled: false
  },
  series: [{
      name: "Sumber Dana",
      data: [800000000, 400000000, 1200000000]
  }],
  colors: ["#007bff"],
  yaxis: {
      axisBorder: {
          show: true,
          color: '#bec7e0',
        },  
        axisTicks: {
          show: true,
          color: '#bec7e0',
      }, 
  },
  xaxis: {
      categories: ['GIRO', 'DEPOSITO', 'INVESTASI'],
      /*labels: {
          show: true,
          formatter: function (val) {
              return  parseFloat(val).toLocaleString('en')
          }
      },*/
  },
  states: {
      hover: {
          filter: 'none'
      }
  },
  grid: {
      borderColor: '#f1f3fa'
  },

  tooltip: {
    y: {
        formatter: function (val) {
            return  ''+parseFloat(val).toLocaleString('en')
        }
    }
  }
}

var chart = new ApexCharts(document.querySelector("#diagram_saldo"), options);
chart.render();

//penerimaan sumber dana
var options = {
    chart: {
        height: 350,
        type: 'bar',
        toolbar: {
          show: true,
          tools: {
            download: true,
            selection: true,
          }, 
        },
    },
    plotOptions: {
        bar: {
            horizontal: true,
        }
    },
    dataLabels: {
        enabled: false
    },
    series: [{
        name: "Sumber Dana Penerimaan",
        data: [400000000, 300000000, 150000000, 150000000]
    }],
    colors: ["#1ecab8"],
    yaxis: {
        axisBorder: {
            show: true,
            color: '#bec7e0',
          },  
          axisTicks: {
            show: true,
            color: '#bec7e0',
        }, 
    },
    xaxis: {
        categories: ['BPP', 'UPP', 'KEMITRAAN', 'BEASISWA'],
    },
    states: {
        hover: {
            filter: 'none'
        }
    },
    grid: {
        borderColor: '#f1f3fa'
    },

    tooltip: {
      y: {
          formatter: function (val) {
              return  ''+parseFloat(val).toLocaleString('en')
          }
      }
    },
}

var chart = new ApexCharts(document.querySelector("#dashboard_penerimaan_sumberdana"), options);
chart.render();

//pengeluaran
var options = {
  chart: {
      height: 300,
      type: 'donut',
      toolbar: {
        show: true,
        tools: {
          download: true,
          selection: true,
        }, 
      },
  }, 
  plotOptions: {
    pie: {
      donut: {
        size: '60%'
      }
    }
  },
  dataLabels: {
    enabled: false,
  },
 
  series: [1500000000, 1000000000],
  colors: ["#1ecab8", "#f3c74d"],
  legend: {
      show: true,
      position: 'bottom',
      horizontalAlign: 'center',
      verticalAlign: 'middle',
      floating: false,
      fontSize: '14px',
      offsetX: 0,
      offsetY: -13
  },
  labels: ["BNI", "BJB"],
 
  responsive: [{
      breakpoint: 600,
      options: {
        plotOptions: {
            donut: {
              customScale: 0.2
            }
          },        
          chart: {
              height: 240
          },
          legend: {
              show: false
          },
      }
  }],

  tooltip: {
    y: {
        formatter: function (val) {
            return  parseFloat(val).toLocaleString('en')
        }
    }
  }
  
}

var chart = new ApexCharts(document.querySelector("#dashboard_pengeluaran"), options);
chart.render();


//pengeluaran jenis
var options = {
    chart: {
        height: 350,
        type: 'bar',
        toolbar: {
          show: true,
          tools: {
            download: true,
            selection: true,
          }, 
        },
    },
    plotOptions: {
        bar: {
            horizontal: true,
        }
    },
    dataLabels: {
        enabled: false
    },
    series: [{
        name: "Jenis Pengeluaran",
        data: [300000000, 300000000, 200000000, 100000000, 100000000]
    }],
    colors: ["#f1646c"],
    yaxis: {
        axisBorder: {
            show: true,
            color: '#bec7e0',
          },  
          axisTicks: {
            show: true,
            color: '#bec7e0',
        }, 
    },
    xaxis: {
        categories: ['PEGAWAI', 'MODAL', 'BARANG', 'JASA', 'LAIN-LAIN'],     
    },
    states: {
        hover: {
            filter: 'none'
        }
    },
    grid: {
        borderColor: '#f1f3fa'
    },

    tooltip: {
      y: {
          formatter: function (val) {
              return  ''+parseFloat(val).toLocaleString('en')
          }
      }
    }
}

var chart = new ApexCharts(document.querySelector("#dashboard_pengeluaran_jenis"), options);
chart.render();
