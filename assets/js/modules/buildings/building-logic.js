/**
 * Logica degli edifici: nomi, costi, tempi, benefici
 */

/**
 * Ottiene il nome dell'edificio in italiano
 */
function getBuildingName(type) {
    const buildingNames = {
        'house': 'Casa',
        'farm': 'Fattoria',
        'woodcutter': 'Falegnameria',
        'quarry': 'Cava di Pietra',
        'well': 'Pozzo',
        'hunting_lodge': 'Capanno di Caccia',
        'water_tank': 'Serbatoio d\'Acqua',
        'mill': 'Mulino',
        'water_pipes': 'Condutture d\'Acqua'
    };
    
    return buildingNames[type] || type;
}

/**
 * Calcola i costi di potenziamento per un edificio
 */
function getUpgradeCosts(type, level) {
    // Costi base per livello 1
    const baseCosts = {
        house: { wood: 50, stone: 30 },
        farm: { wood: 40, stone: 30 },
        woodcutter: { wood: 30, stone: 40 },
        quarry: { wood: 50, stone: 0 },
        well: { wood: 35, stone: 45 },
        hunting_lodge: { wood: 80, stone: 60 },
        water_tank: { wood: 100, stone: 150 },
        mill: { wood: 90, stone: 70 },
        water_pipes: { wood: 120, stone: 100 }
    };
    
    // Calcola costi in base al livello (ogni livello costa 1.5x il precedente)
    const costs = {};
    const baseCost = baseCosts[type] || { wood: 50, stone: 50 };
    
    for (const [resource, amount] of Object.entries(baseCost)) {
        costs[resource] = Math.floor(amount * Math.pow(1.5, level - 1));
    }
    
    return costs;
}

/**
 * Calcola il tempo di potenziamento
 */
function getUpgradeTime(type, level) {
    // Tempi base in minuti
    const baseTimesMinutes = {
        house: 30,
        farm: 45,
        woodcutter: 40,
        quarry: 50,
        well: 20,
        hunting_lodge: 35,
        water_tank: 40,
        mill: 60,
        water_pipes: 60
    };
    
    // Conversione in secondi e scala per livello
    const baseTime = (baseTimesMinutes[type] || 30) * 60;
    return Math.floor(baseTime * Math.pow(1.2, level - 1));
}

/**
 * Ottiene i benefici del potenziamento per un determinato tipo di edificio
 */
function getBuildingUpgradeBenefits(type, nextLevel) {
    let benefits = '';
    
    switch (type) {
        case 'house':
            benefits = `
                <li>+5 abitanti</li>
                <li>+${nextLevel}% produzione risorse base</li>
            `;
            break;
        case 'farm':
            benefits = `
                <li>+10 cibo/ora (${15 + 10 * (nextLevel - 1)} totale)</li>
                <li>+1 lavoratore</li>
            `;
            break;
        case 'woodcutter':
            benefits = `
                <li>+12 legno/ora (${20 + 12 * (nextLevel - 1)} totale)</li>
                <li>+1 lavoratore</li>
            `;
            break;
        case 'quarry':
            benefits = `
                <li>+9 pietra/ora (${15 + 9 * (nextLevel - 1)} totale)</li>
                <li>+1 lavoratore</li>
            `;
            break;
        case 'well':
            benefits = `
                <li>+7 acqua/ora (${10 + 7 * (nextLevel - 1)} totale)</li>
                <li>+${nextLevel}% capacità di stoccaggio dell'acqua</li>
            `;
            break;
        case 'hunting_lodge':
            benefits = `
                <li>+8 cibo/ora (${12 + 8 * (nextLevel - 1)} totale)</li>
                <li>+${5 * nextLevel}% possibilità di ottenere carne pregiata</li>
            `;
            break;
        case 'water_tank':
            benefits = `
                <li>+300 capacità acqua (${500 + 300 * (nextLevel - 1)} totale)</li>
                <li>+${4 * nextLevel} ore di ritardo per effetti negativi carenza acqua</li>
            `;
            break;
        case 'mill':
            benefits = `
                <li>+${50 + 2 * nextLevel}% efficienza del cibo</li>
                <li>+${nextLevel} lavoratori</li>
            `;
            break;
        case 'water_pipes':
            benefits = `
                <li>+${20 + 5 * nextLevel}% efficienza pozzi</li>
                <li>-${5 * nextLevel}% consumo acqua abitanti</li>
            `;
            break;
        default:
            benefits = '<li>Potenziamento generico</li>';
    }
    
    return benefits;
}

/**
 * Ottiene le statistiche per un determinato edificio
 */
function getBuildingStats(building) {
    let stats = '';
    
    switch (building.type) {
        case 'house':
            stats = `
                <li>Abitanti: +${5 * building.level}</li>
                <li>Bonus produzione: +${building.level}%</li>
            `;
            break;
        case 'farm':
            stats = `
                <li>Produzione: ${15 + 10 * (building.level - 1)} cibo/ora</li>
                <li>Lavoratori: ${2 + building.level - 1}</li>
            `;
            break;
        case 'woodcutter':
            stats = `
                <li>Produzione: ${20 + 12 * (building.level - 1)} legno/ora</li>
                <li>Lavoratori: ${3 + building.level - 1}</li>
            `;
            break;
        case 'quarry':
            stats = `
                <li>Produzione: ${15 + 9 * (building.level - 1)} pietra/ora</li>
                <li>Lavoratori: ${3 + building.level - 1}</li>
            `;
            break;
        case 'well':
            stats = `
                <li>Produzione: ${10 + 7 * (building.level - 1)} acqua/ora</li>
                <li>Bonus stoccaggio: +${building.level}%</li>
            `;
            break;
        case 'hunting_lodge':
            stats = `
                <li>Produzione: ${12 + 8 * (building.level - 1)} cibo/ora</li>
                <li>Possibilità carne pregiata: ${5 * building.level}%</li>
            `;
            break;
        case 'water_tank':
            stats = `
                <li>Capacità aggiuntiva: ${500 + 300 * (building.level - 1)} acqua</li>
                <li>Ritardo carenza: ${4 * building.level} ore</li>
            `;
            break;
        case 'mill':
            stats = `
                <li>Efficienza cibo: +${50 + 2 * building.level}%</li>
                <li>Lavoratori: ${building.level}</li>
            `;
            break;
        case 'water_pipes':
            stats = `
                <li>Efficienza pozzi: +${20 + 5 * building.level}%</li>
                <li>Riduzione consumo: -${5 * building.level}%</li>
            `;
            break;
        default:
            stats = '<li>Nessuna statistica disponibile</li>';
    }
    
    return stats;
}

// Rendi disponibili globalmente
window.getBuildingName = getBuildingName;
window.getUpgradeCosts = getUpgradeCosts;
window.getUpgradeTime = getUpgradeTime;
window.getBuildingUpgradeBenefits = getBuildingUpgradeBenefits;
window.getBuildingStats = getBuildingStats;