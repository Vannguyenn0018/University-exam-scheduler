import random

def tournament_selection(population, tournament_size=3):
    """Đấu vòng (Tournament Selection)"""
    tournament = random.sample(population, tournament_size)
    return max(tournament, key=lambda chrom: chrom.fitness)

def roulette_wheel_selection(population):
    """Vòng quay Roulette (Roulette Wheel Selection)"""
    total_fitness = sum(chrom.fitness for chrom in population)
    if total_fitness == 0:
        return random.choice(population)
        
    pick = random.uniform(0, total_fitness)
    current = 0
    for chrom in population:
        current += chrom.fitness
        if current > pick:
            return chrom
    return population[-1]