<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Http\Requests\EmployeeRequest;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class EmployeeController extends Controller
{
    const key = 'emp_key';
    const key_created = 'emp_created_key';
    const key_deleted = 'emp_deleted_key';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Redis Hashes
        $employee = Redis::hgetall(self::key);
        if(!$employee)
        {
            $employee = Employee::all();
            if($employee->isNotEmpty())
            {
                foreach($employee as $e)
                {
                    Redis::hset(self::key, $e->id, json_encode($e));
                }
            }
        }
        else
        {
            $employee = array_map(function($e){
                return json_decode($e);
            }, $employee);
        }

        // Redis Expire
        Redis::expire(self::key, 60);

        // Redis Queue
        $queueName = 'tasks';
        // Add task to the Redis queue
        for($i=0; $i<10; $i++)
        {
            Redis::rpush($queueName, 'Task-'. $i);
        }

        return view('employees.index', ['Employes' => $employee]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('employees.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmployeeRequest $request)
    {
        $employee = Employee::create($request->validated());

        // Redis Hashes
        Redis::hset(self::key, $employee->id, json_encode($employee));

        // Redis INCR
        Redis::incr(self::key_created);

        return redirect('/employees');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        return view('employees.show', compact('employee'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        return view('employees.edit', compact('employee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmployeeRequest $request, Employee $employee)
    {
        //Redis Lock
        $lock = Redis::set(self::key, true, 'EX', 5, 'NX'); // lock will expires 5s
        
        if(!$lock)
        {
            $employee->update([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'address' => $request->address,
                'phone' => $request->phone
            ]);

            sleep(5);

            // Redis Hashes
            Redis::hset(self::key, $employee->id, json_encode($employee));
        }

        return redirect('/employees');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        // Redis Hashes
        Redis::hdel(self::key, $employee->id);

         // Redis INCRBY
         Redis::incrby(self::key_deleted, 1);

        return back();
    }
}
