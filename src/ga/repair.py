
def repair_chromosome(chromosome, scheduler):
    """
    Toán tử sửa chữa (Repair Operator).
    Sử dụng Sequential Search để nắn chỉnh các lỗi sinh ra sau quá trình di truyền.
    """
    # Mapping tạm để tăng tốc kiểm tra lỗi trùng phòng
    timeslot_section_map = {}
    for gene in chromosome.genes:
        if gene.timeslot_id not in timeslot_section_map:
            timeslot_section_map[gene.timeslot_id] = []
        timeslot_section_map[gene.timeslot_id].append(gene)
        
    for i, gene in enumerate(chromosome.genes):
        is_violated = False
        num_students = scheduler.sections.get(gene.section_id, 0)
        
        # 1. Quét lỗi sức chứa
        total_cap = sum(scheduler.rooms.get(r, 0) for r in gene.room_ids)
        if total_cap < num_students:
            is_violated = True
            
        # 2. Quét lỗi trùng không gian (phòng thi)
        if not is_violated:
            for other_gene in timeslot_section_map[gene.timeslot_id]:
                if other_gene != gene and set(other_gene.room_ids).intersection(set(gene.room_ids)):
                    is_violated = True
                    break
                    
        # 3. Quét lỗi trùng lịch sinh viên
        if not is_violated:
            for student, enrolled_sections in scheduler.enrollments.items():
                if gene.section_id in enrolled_sections:
                    for other_sec in enrolled_sections:
                        if other_sec != gene.section_id:
                            other_ts = next((g.timeslot_id for g in chromosome.genes if g.section_id == other_sec), None)
                            if other_ts == gene.timeslot_id:
                                is_violated = True
                                break
                if is_violated:
                    break
                    
        # Sửa lỗi: Duyệt lại các phòng/ca thi hợp lệ (Greedy/Heuristic)
        if is_violated:
            repaired = False
            for ts in scheduler.timeslots:
                if repaired: break
                for room, cap in scheduler.rooms.items():
                    # Tìm một phòng đơn lẻ đủ lớn. (Phiên bản phức tạp hơn có thể nối nhiều phòng ở đây)
                    if cap >= num_students:
                        chromosome.genes[i] = Gene(gene.section_id, ts, [room])
                        repaired = True
                        break