import random

def uniform_crossover(parent1, parent2):
    """
    Toán tử lai ghép đồng nhất (Uniform Crossover).
    Lai ghép trực tiếp trên từng lớp học phần giữa hai cá thể cha mẹ.
    """
    child1 = Chromosome()
    child2 = Chromosome()
    
    for g1, g2 in zip(parent1.genes, parent2.genes):
        if random.random() < 0.5:
            child1.add_gene(g1.clone())
            child2.add_gene(g2.clone())
        else:
            child1.add_gene(g2.clone())
            child2.add_gene(g1.clone())
            
    return child1, child2

def single_point_crossover(parent1, parent2):
    """Toán tử lai ghép lai chéo 1 điểm."""
    point = random.randint(1, len(parent1.genes) - 1)
    
    child1_genes = [g.clone() for g in parent1.genes[:point]] + [g.clone() for g in parent2.genes[point:]]
    child2_genes = [g.clone() for g in parent2.genes[:point]] + [g.clone() for g in parent1.genes[point:]]
    
    return Chromosome(child1_genes), Chromosome(child2_genes)