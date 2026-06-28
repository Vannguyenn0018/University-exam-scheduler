class FitnessCalculator:
    def __init__(self, scheduler, w_hard=1000, w_soft=1):
        self.scheduler = scheduler
        self.w_hard = w_hard  # Trọng số áp đảo cho lỗi cứng
        self.w_soft = w_soft  # Trọng số cho lỗi mềm (có thể bổ sung sau)

    def calculate_fitness(self, chromosome):
        p_student = 0
        p_room = 0
        p_capacity = 0
        p_soft = 0
        
        timeslot_section_map = {}
        for gene in chromosome.genes:
            if gene.timeslot_id not in timeslot_section_map:
                timeslot_section_map[gene.timeslot_id] = []
            timeslot_section_map[gene.timeslot_id].append(gene)
            
            # 1. Ràng buộc sức chứa
            total_capacity = sum(self.scheduler.rooms.get(r, 0) for r in gene.room_ids)
            num_students = self.scheduler.sections.get(gene.section_id, 0)
            if total_capacity < num_students:
                p_capacity += (num_students - total_capacity)

        # 2. Xung đột không gian (trùng phòng thi)
        for ts, genes in timeslot_section_map.items():
            used_rooms = set()
            for gene in genes:
                for room_id in gene.room_ids:
                    if room_id in used_rooms:
                        p_room += 1
                    used_rooms.add(room_id)

        # 3. Xung đột lịch sinh viên
        for student_id, enrolled_sections in self.scheduler.enrollments.items():
            student_timeslots = set()
            for section_id in enrolled_sections:
                gene = chromosome.get_gene_by_section(section_id)
                if gene:
                    if gene.timeslot_id in student_timeslots:
                        p_student += 1
                    student_timeslots.add(gene.timeslot_id)

        p_hard = p_student + p_room + p_capacity
        penalty = self.w_hard * p_hard + self.w_soft * p_soft
        
        # Fitness sẽ chạy từ (0, 1]. Số 1 là hoàn hảo
        chromosome.fitness = 1 / (1 + penalty)
        chromosome.conflicts = p_hard  # Lưu lại tổng lỗi để log ra màn hình
        
        return chromosome.fitness